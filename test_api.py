#!/usr/bin/env python3
"""Local smoke test for the Intermediarius Laravel API."""

from __future__ import annotations

import argparse
import binascii
import hashlib
import hmac
import ipaddress
import json
import os
import random
import struct
import sys
import uuid
import zlib
from datetime import date, datetime, time, timedelta, timezone
from urllib.parse import urlsplit

import requests


ROLES = ("client", "owner", "agent", "admin")
ROLE_EMAILS = {
    "client": "cliente@intermediarius.test",
    "owner": "proprietario@intermediarius.test",
    "agent": "agente@intermediarius.test",
    "admin": "admin@intermediarius.test",
}
BASE_READS = (
    ("GET", "/properties/mine"),
    ("GET", "/favorites"),
    ("GET", "/agent-links"),
    ("GET", "/availability"),
    ("GET", "/visits"),
    ("GET", "/rentals"),
    ("GET", "/commissions"),
    ("GET", "/community/requests"),
    ("GET", "/notifications"),
)
ADMIN_READS = (
    ("GET", "/admin/stats"),
    ("GET", "/admin/users"),
    ("GET", "/admin/properties"),
    ("GET", "/admin/payments"),
    ("GET", "/admin/settings"),
)
WEBHOOK_HEADER = os.getenv("API_TEST_SIGNATURE_HEADER", "X-Emis-Signature")
WEBHOOK_SECRET = os.getenv(
    "API_TEST_WEBHOOK_SECRET",
    os.getenv("EMIS_GPO_WEBHOOK_SECRET", "local-fake-secret"),
)
ANGOLA_TZ = timezone(timedelta(hours=1))


def png_pixel() -> bytes:
    """Build a valid 1x1 PNG using only the Python standard library."""

    def chunk(name: bytes, content: bytes) -> bytes:
        checksum = binascii.crc32(name + content) & 0xFFFFFFFF
        return struct.pack(">I", len(content)) + name + content + struct.pack(">I", checksum)

    header = struct.pack(">IIBBBBB", 1, 1, 8, 6, 0, 0, 0)
    pixel = zlib.compress(b"\x00\x40\x80\xc0\xff")
    return (
        b"\x89PNG\r\n\x1a\n"
        + chunk(b"IHDR", header)
        + chunk(b"IDAT", pixel)
        + chunk(b"IEND", b"")
    )


class ApiTester:
    def __init__(
        self,
        base_url: str,
        timeout: float,
        write: bool,
        simulate_payments: bool,
        keep_property: bool,
    ):
        parsed = urlsplit(base_url)
        hostname = parsed.hostname or ""
        try:
            is_loopback = ipaddress.ip_address(hostname).is_loopback
        except ValueError:
            is_loopback = hostname.lower() == "localhost"
        if parsed.scheme not in ("http", "https") or not is_loopback:
            raise ValueError("Por segurança, o script só aceita URL local (localhost/loopback).")

        api_root = base_url.rstrip("/")
        if not api_root.endswith("/api/v1"):
            api_root += "/api/v1"

        self.api_root = api_root
        self.timeout = timeout
        self.write = write
        self.simulate_payments = simulate_payments
        self.keep_property = keep_property
        self.sessions: dict[str, requests.Session] = {}
        self.results: list[tuple[str, str]] = []
        self.test_id = uuid.uuid4().hex[:10]

    def record(self, passed: bool, label: str, detail: str = "") -> None:
        status = "PASS" if passed else "FAIL"
        self.results.append((status, label))
        suffix = f" — {detail}" if detail else ""
        print(f"{status:4} {label}{suffix}")

    def skip(self, label: str, why: str) -> None:
        self.results.append(("SKIP", label))
        print(f"SKIP {label} — {why}")

    def session(self, role: str | None) -> requests.Session:
        key = role or "guest"
        if key not in self.sessions:
            self.sessions[key] = requests.Session()
            self.sessions[key].headers.update({"Accept": "application/json"})
        return self.sessions[key]

    @staticmethod
    def response_json(response: requests.Response | None) -> dict | None:
        if response is None:
            return None
        try:
            payload = response.json()
        except ValueError:
            return None
        return payload if isinstance(payload, dict) else None

    def call(
        self,
        role: str | None,
        method: str,
        path: str,
        expected: int | tuple[int, ...],
        *,
        params: dict | None = None,
        json_body: dict | None = None,
        data: dict | None = None,
        files: list | None = None,
        raw_body: bytes | None = None,
        headers: dict | None = None,
    ) -> requests.Response | None:
        label = f"{role or 'guest'} {method} {path}"
        wanted = (expected,) if isinstance(expected, int) else expected
        try:
            response = self.session(role).request(
                method,
                self.api_root + path,
                params=params,
                json=json_body,
                data=raw_body if raw_body is not None else data,
                files=files,
                headers=headers,
                timeout=self.timeout,
                allow_redirects=False,
            )
        except requests.RequestException as error:
            self.record(False, label, f"request error: {error.__class__.__name__}")
            return None

        if response.status_code in wanted:
            self.record(True, label, f"HTTP {response.status_code}")
            return response

        payload = self.response_json(response) or {}
        safe_detail = payload.get("code") or payload.get("message") or "unexpected status"
        self.record(
            False,
            label,
            f"HTTP {response.status_code}; esperado {wanted}; {safe_detail}",
        )
        return response

    def assert_true(self, label: str, condition: bool, detail: str = "") -> None:
        self.record(condition, label, detail if not condition else "")

    def login(self, role: str, email: str, password: str) -> dict | None:
        response = self.call(
            None,
            "POST",
            "/auth/login",
            200,
            json_body={"email": email, "password": password},
        )
        payload = self.response_json(response)
        token = payload.get("token") if payload else None
        user = payload.get("user") if payload else None
        if not token or not isinstance(user, dict):
            self.record(False, f"{role} login token shape", "resposta sem token/user")
            return None

        session = self.session(role)
        session.headers.update({"Authorization": f"Bearer {token}"})
        self.record(True, f"{role} login token shape")
        return user

    @staticmethod
    def records(payload: dict | None) -> list[dict]:
        if not payload:
            return []
        records = payload.get("data", [])
        return records if isinstance(records, list) else []

    def require_id(self, payload: dict | None, label: str) -> int | None:
        value = payload.get("id") if payload else None
        if isinstance(value, int):
            return value
        self.record(False, label, "resposta sem id")
        return None

    def property_files(self) -> list[tuple[str, tuple[str, bytes, str]]]:
        image = png_pixel()
        return [
            ("photos[]", (f"api-test-{self.test_id}-{index}.png", image, "image/png"))
            for index in range(5)
        ]

    def property_form(self, address: str) -> dict[str, str]:
        return {
            "title": f"API smoke {self.test_id}",
            "category": "rent",
            "type": "Apartamento",
            "price": "250000",
            "province": "Luanda",
            "municipality": "Talatona",
            "neighborhood": "Benfica",
            "bedrooms": "2",
            "baths": "1",
            "area": "90",
            "private_address": address,
            "private_lat": "-8.8390000",
            "private_lng": "13.2894000",
            "description": "Imóvel de teste automatizado local.",
            "amenities[]": "estacionamento",
        }

    def future_slot(self) -> tuple[list[int], str]:
        now = datetime.now(ANGOLA_TZ)
        day_offset = random.randint(7, 28)
        scheduled_day = now.date() + timedelta(days=day_offset)
        while scheduled_day.isoweekday() > 5:
            scheduled_day += timedelta(days=1)
        hour = random.choice((10, 11, 13, 14, 15))
        minute = random.choice((0, 30))
        scheduled = datetime.combine(scheduled_day, time(hour, minute), ANGOLA_TZ)
        return [scheduled_day.isoweekday()], scheduled.isoformat()

    def confirm_fake_payment(self, payment: dict | None, label: str) -> bool:
        if not self.simulate_payments:
            self.skip(f"{label} paid webhook", "use --simulate-fake-payments para fluxo fake local")
            return False
        if not payment or not payment.get("reference"):
            keys = sorted(payment.keys()) if isinstance(payment, dict) else []
            self.record(
                False,
                f"{label} payment data",
                f"referência ausente; campos recebidos: {keys}",
            )
            return False

        event_id = f"api-test-{uuid.uuid4().hex}"
        payload = {
            "event_id": event_id,
            "reference": payment["reference"],
            "amount": int(payment["amount"]),
            "currency": payment["currency"],
            "entity": payment.get("entity"),
            "status": "paid",
        }
        raw = json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
        signature = hmac.new(WEBHOOK_SECRET.encode("utf-8"), raw, hashlib.sha256).hexdigest()
        signed_headers = {WEBHOOK_HEADER: signature, "Content-Type": "application/json"}
        first = self.call(
            None,
            "POST",
            "/payments/emis/webhook",
            200,
            raw_body=raw,
            headers=signed_headers,
        )
        if first is None or first.status_code != 200:
            return False
        duplicate = self.call(
            None,
            "POST",
            "/payments/emis/webhook",
            200,
            raw_body=raw,
            headers=signed_headers,
        )
        return duplicate is not None and duplicate.status_code == 200

    def run_reads(self, user_ids: dict[str, int]) -> dict | None:
        self.call(None, "GET", "/properties", 200, params={"per_page": 2})
        self.call(None, "GET", "/auth/me", 401)
        self.call(
            None,
            "POST",
            "/payments/emis/webhook",
            401,
            raw_body=b"{}",
            headers={WEBHOOK_HEADER: "invalid-signature", "Content-Type": "application/json"},
        )

        listing = self.call(
            None,
            "GET",
            "/properties",
            200,
            params={
                "q": "Apartamento",
                "category": "rent",
                "province": "Luanda",
                "municipality": "Talatona",
                "min_price": 1,
                "max_price": 100000000,
                "bedrooms": 1,
                "sort": "newest",
                "page": 1,
                "per_page": 2,
            },
        )
        listing_payload = self.response_json(listing)
        public_records = self.records(listing_payload)

        for role in ROLES:
            if role not in self.sessions:
                self.skip(f"{role} protected GET routes", "login não disponível")
                continue
            self.call(role, "GET", "/auth/me", 200)
            for method, path in BASE_READS:
                self.call(role, method, path, 200)
            self.call(role, "GET", "/properties", 200, params={"per_page": 2})

        if public_records:
            first_id = public_records[0].get("id")
            if first_id:
                detail = self.call(None, "GET", f"/properties/{first_id}", 200)
                detail_payload = self.response_json(detail) or {}
                self.assert_true(
                    "public property hides exact address",
                    "private_address" not in detail_payload,
                )
                return detail_payload
        if not self.write:
            self.skip("GET /properties/{id}", "não há imóvel ativo público para usar")
        return None

    def run_admin_reads(self) -> None:
        for method, path in ADMIN_READS:
            self.call("admin", method, path, 200)
            for role in ("client", "owner", "agent"):
                if role in self.sessions:
                    self.call(role, method, path, 403)

    def run_write_workflow(self, user_ids: dict[str, int]) -> None:
        if not self.write:
            for method, path in (
                ("POST", "/auth/register"),
                ("POST", "/auth/phone-verifications"),
                ("POST", "/auth/phone-verifications/verify"),
                ("POST", "/properties"),
                ("DELETE", "/properties/{id}"),
                ("POST", "/properties/{id}/favorite"),
                ("POST", "/properties/{id}/agents"),
                ("PATCH", "/agent-links/{id}"),
                ("PUT", "/availability"),
                ("POST", "/visits"),
                ("POST", "/visits/checkin"),
                ("POST", "/rentals"),
                ("PATCH", "/commissions/{id}/pay"),
                ("POST", "/community/requests"),
                ("POST", "/community/requests/{id}/offers"),
                ("PATCH", "/community/requests/{id}/close"),
                ("PATCH", "/notifications/{id}/read"),
                ("POST", "/notifications/read-all"),
                ("PATCH", "/admin/users/{id}"),
                ("PATCH", "/admin/properties/{id}"),
                ("PUT", "/admin/settings"),
            ):
                self.skip(f"{method} {path}", "use --write para permitir alterações locais")
            return

        self.run_auth_writes()
        address = f"Rua API Teste {self.test_id}"
        property_response = self.call(
            "owner",
            "POST",
            "/properties",
            201,
            data=self.property_form(address),
            files=self.property_files(),
        )
        property_payload = self.response_json(property_response)
        property_id = self.require_id(property_payload, "created property id")
        if property_id is None:
            self.skip("property-dependent routes", "não foi possível criar o imóvel de teste")
            return

        self.call(
            "owner",
            "POST",
            "/properties",
            409,
            data=self.property_form(address),
            files=self.property_files(),
        )
        self.call(
            "client",
            "POST",
            "/properties",
            403,
            data=self.property_form(f"Rua não autorizada {self.test_id}"),
            files=self.property_files(),
        )

        self.call(
            "admin",
            "PATCH",
            f"/admin/users/{user_ids['owner']}",
            200,
            json_body={"status": "active"},
        )
        self.call(
            "admin",
            "PATCH",
            f"/admin/properties/{property_id}",
            200,
            json_body={"status": "active"},
        )

        public_detail = self.call(None, "GET", f"/properties/{property_id}", 200)
        public_payload = self.response_json(public_detail) or {}
        self.assert_true(
            "new public property hides exact address",
            "private_address" not in public_payload,
        )
        client_detail = self.call(
            "client",
            "GET",
            f"/properties/{property_id}",
            200,
        )
        self.assert_true(
            "client cannot see exact address before payment",
            "private_address" not in (self.response_json(client_detail) or {}),
        )
        public_listing = self.call(
            None,
            "GET",
            "/properties",
            200,
            params={"q": f"API smoke {self.test_id}", "category": "rent"},
        )
        self.assert_true(
            "approved property appears in public listing",
            any(item.get("id") == property_id for item in self.records(self.response_json(public_listing))),
        )
        self.call("owner", "DELETE", f"/properties/{property_id}", 403)
        self.call(
            "client",
            "POST",
            f"/properties/{property_id}/favorite",
            200,
        )
        self.call("client", "GET", "/favorites", 200)
        self.call(
            "client",
            "POST",
            f"/properties/{property_id}/favorite",
            200,
        )

        self.call(
            "agent",
            "POST",
            f"/properties/{property_id}/agents",
            403,
            json_body={
                "agent_email": ROLE_EMAILS["agent"],
                "commission_pct": 5,
                "terms": "Smoke test",
            },
        )
        invite = self.call(
            "owner",
            "POST",
            f"/properties/{property_id}/agents",
            201,
            json_body={
                "agent_email": ROLE_EMAILS["agent"],
                "commission_pct": 5,
                "terms": "Smoke test",
            },
        )
        invite_payload = self.response_json(invite)
        link_id = self.require_id(invite_payload, "agent link id")
        if link_id is None:
            self.skip("agent link transitions and visits", "convite não criado")
            return

        self.call(
            "owner",
            "PATCH",
            f"/agent-links/{link_id}",
            403,
            json_body={"status": "approved"},
        )
        self.call(
            "agent",
            "PATCH",
            f"/agent-links/{link_id}",
            200,
            json_body={"status": "approved"},
        )
        self.call("owner", "GET", "/agent-links", 200)

        days, scheduled_at = self.future_slot()
        availability = {
            "days": days,
            "start": "09:00",
            "end": "17:00",
            "slot": 30,
        }
        self.call("agent", "PUT", "/availability", 200, json_body=availability)
        self.call("agent", "GET", "/availability", 200)

        visit_response = self.call(
            "client",
            "POST",
            "/visits",
            201,
            json_body={
                "property_id": property_id,
                "scheduled_at": scheduled_at,
                "payment_method": "reference",
            },
        )
        visit_payload = self.response_json(visit_response)
        visit_id = self.require_id(visit_payload, "visit id")
        self.call("agent", "POST", "/visits/checkin", 422, json_body={"code": "not-a-valid-code"})
        self.call("agent", "GET", "/visits", 200)

        if visit_payload:
            payment = visit_payload.get("payment")
            if self.simulate_payments:
                self.confirm_fake_payment(payment, "visit")
                visits_response = self.call("client", "GET", "/visits", 200)
                visits = self.records(self.response_json(visits_response))
                paid_visit = next((item for item in visits if item.get("id") == visit_id), {})
                code = paid_visit.get("code")
                self.assert_true("paid visit receives check-in code", bool(code))
                if code:
                    unlocked = self.call("client", "GET", f"/properties/{property_id}", 200)
                    unlocked_payload = self.response_json(unlocked) or {}
                    self.assert_true(
                        "paid visitor can see exact address",
                        unlocked_payload.get("private_address") == address,
                    )
                    self.call(
                        "agent",
                        "POST",
                        "/visits/checkin",
                        200,
                        json_body={"code": code},
                    )
                    self.call(
                        "agent",
                        "POST",
                        "/visits/checkin",
                        409,
                        json_body={"code": code},
                    )
            else:
                self.skip("paid visit code/private address/check-in", "use --simulate-fake-payments")
        else:
            self.skip("visit payment/check-in lifecycle", "visita não criada")

        community_response = self.call(
            "client",
            "POST",
            "/community/requests",
            201,
            json_body={
                "category": "rent",
                "tipologia": "T2",
                "municipality": "Talatona",
                "neighborhood": "Benfica",
                "budget": 300000,
                "message": f"Pedido de teste {self.test_id}",
            },
        )
        community_payload = self.response_json(community_response)
        request_id = self.require_id(community_payload, "community request id")
        if request_id is not None:
            self.call(
                "agent",
                "POST",
                f"/community/requests/{request_id}/offers",
                201,
                json_body={"property_id": property_id, "accept": True},
            )
            self.call(
                "agent",
                "POST",
                f"/community/requests/{request_id}/offers",
                409,
                json_body={"property_id": property_id, "accept": True},
            )
            self.call("client", "GET", "/community/requests", 200)
            self.call(
                "client",
                "PATCH",
                f"/community/requests/{request_id}/close",
                200,
            )
            self.call(
                "owner",
                "POST",
                f"/community/requests/{request_id}/offers",
                422,
                json_body={"property_id": property_id, "accept": False},
            )

        rental_response = self.call(
            "client",
            "POST",
            "/rentals",
            201,
            json_body={
                "property_id": property_id,
                "tenant": f"API Tester {self.test_id}",
                "phone": "+244 923 000 000",
                "nif": "123456789",
                "start_date": (date.today() + timedelta(days=30)).isoformat(),
                "duration_months": 12,
                "payment_method": "mcx_express",
            },
        )
        rental_payload = self.response_json(rental_response)
        rental_id = self.require_id(rental_payload, "rental id")
        if rental_payload and self.simulate_payments and not self.keep_property:
            self.confirm_fake_payment(rental_payload.get("payment"), "rental")
        elif rental_payload and self.simulate_payments:
            self.skip(
                "rental activation",
                "--keep-property evita mudar o imóvel para rented",
            )
        elif rental_payload:
            self.skip("rental activation and commission creation", "use --simulate-fake-payments")

        self.call("client", "GET", "/rentals", 200)
        self.call("owner", "GET", "/rentals", 200)

        if rental_id is not None and self.simulate_payments and not self.keep_property:
            commissions_response = self.call("owner", "GET", "/commissions", 200)
            commissions = self.records(self.response_json(commissions_response))
            commission = next(
                (item for item in commissions if item.get("rental_id") == rental_id),
                None,
            )
            if commission:
                commission_id = commission.get("id")
                self.call(
                    "client",
                    "PATCH",
                    f"/commissions/{commission_id}/pay",
                    403,
                    json_body={"payment_method": "mcx_express"},
                )
                paid_commission = self.call(
                    "owner",
                    "PATCH",
                    f"/commissions/{commission_id}/pay",
                    200,
                    json_body={"payment_method": "mcx_express"},
                )
                paid_commission_payload = self.response_json(paid_commission) or {}
                self.confirm_fake_payment(paid_commission_payload.get("payment"), "commission")
            else:
                self.record(False, "commission created after rental payment", "comissão não encontrada")
        elif self.keep_property:
            self.skip(
                "commission payment routes",
                "o fluxo depende de ativar o arrendamento, que mudaria o estado do imóvel preservado",
            )
        elif not self.simulate_payments:
            self.skip("commission payment routes", "comissão depende de webhook de arrendamento pago")

        self.call("agent", "GET", "/commissions", 200)
        self.call("admin", "GET", "/admin/payments", 200)

        notifications_response = self.call("client", "GET", "/notifications", 200)
        notifications = self.records(self.response_json(notifications_response))
        if notifications:
            notification_id = notifications[0].get("id")
            self.call(
                "admin",
                "PATCH",
                f"/notifications/{notification_id}/read",
                403,
            )
            self.call(
                "client",
                "PATCH",
                f"/notifications/{notification_id}/read",
                200,
            )
        else:
            self.skip("PATCH /notifications/{id}/read", "não há notificação de teste")
        self.call("client", "POST", "/notifications/read-all", 204)

        settings_response = self.call("admin", "GET", "/admin/settings", 200)
        settings = self.response_json(settings_response)
        if settings:
            self.call(
                "admin",
                "PUT",
                "/admin/settings",
                200,
                json_body=settings,
            )
        else:
            self.skip("PUT /admin/settings", "não foi possível ler settings")

        if self.keep_property:
            self.skip(
                f"DELETE /properties/{property_id}",
                "--keep-property mantém o imóvel de teste na API",
            )
        else:
            self.call("admin", "DELETE", f"/properties/{property_id}", 204)
        self.call(
            "owner",
            "PATCH",
            f"/agent-links/{link_id}",
            200,
            json_body={"status": "revoked"},
        )

    def run_auth_writes(self) -> None:
        if not self.write:
            return

        self.call(
            None,
            "POST",
            "/auth/register",
            422,
            json_body={
                "name": f"API Tester {self.test_id}",
                "email": f"api-test-{self.test_id}@example.test",
                "phone": "+244 923 000 099",
                "role": "client",
                "password": f"Test-{self.test_id}-Pass",
            },
        )
        self.skip(
            "registration success",
            "exige envio de SMS e validação humana do OTP; os testes Laravel cobrem o fluxo sem SMS real",
        )

    def summary(self) -> int:
        passed = sum(status == "PASS" for status, _ in self.results)
        failed = sum(status == "FAIL" for status, _ in self.results)
        skipped = sum(status == "SKIP" for status, _ in self.results)
        print(f"\nResumo: {passed} PASS | {failed} FAIL | {skipped} SKIP")
        return 1 if failed else 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Smoke test das rotas da API Intermediárius (somente localhost)."
    )
    parser.add_argument(
        "--base-url",
        default=os.getenv("API_TEST_BASE_URL", "http://127.0.0.1:8000"),
        help="URL local Laravel (default: http://127.0.0.1:8000)",
    )
    parser.add_argument(
        "--password",
        default=os.getenv("API_TEST_PASSWORD", os.getenv("DEV_SEED_PASSWORD", "password")),
        help="Senha das contas de demonstração (não será exibida).",
    )
    parser.add_argument("--timeout", type=float, default=8.0)
    parser.add_argument(
        "--write",
        action="store_true",
        help="Permite criar/registar dados de teste; alguns registos ficam na base de dados.",
    )
    parser.add_argument(
        "--simulate-fake-payments",
        action="store_true",
        help="Envia webhooks HMAC locais para confirmar pagamentos fake; nunca use EMIS real.",
    )
    parser.add_argument(
        "--keep-property",
        action="store_true",
        help="Mantém o imóvel ativo e visível; não remove nem confirma o arrendamento fake.",
    )
    return parser.parse_args()


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    args = parse_args()
    try:
        tester = ApiTester(
            args.base_url,
            args.timeout,
            args.write,
            args.simulate_fake_payments,
            args.keep_property,
        )
    except ValueError as error:
        print(f"Erro: {error}", file=sys.stderr)
        return 2

    user_ids: dict[str, int] = {}
    for role in ROLES:
        email = os.getenv(f"API_TEST_{role.upper()}_EMAIL", ROLE_EMAILS[role])
        user = tester.login(role, email, args.password)
        if user and isinstance(user.get("id"), int):
            user_ids[role] = user["id"]

    tester.run_reads(user_ids)
    if "admin" in tester.sessions:
        tester.run_admin_reads()
    else:
        tester.skip("admin routes", "login admin falhou")

    if not all(role in tester.sessions for role in ROLES):
        tester.skip("write workflow", "é necessário fazer login com os quatro papéis")
    else:
        tester.run_write_workflow(user_ids)

    return tester.summary()


if __name__ == "__main__":
    raise SystemExit(main())
