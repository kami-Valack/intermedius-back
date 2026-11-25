<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Intermediárius: seeders de desenvolvimento

Configure a ligação à base de dados no `.env` e execute:

```bash
php artisan migrate --seed
```

`DatabaseSeeder` cria as definições iniciais sem substituir valores já configurados. Em `local` e `testing`, também cria contas de demonstração e a disponibilidade do intermediário. Estas contas são fictícias e pré-aprovadas apenas para facilitar testes; o registo normal de proprietário/intermediário continua a exigir revisão. Os dados de demonstração não são semeados noutros ambientes.

### Executar com Docker Compose

Requer Docker Engine e Docker Compose. O Compose inicia a API na porta `3043` e o scheduler Laravel; conecta ao MySQL já existente na VPS pela porta `3306`. Na primeira inicialização gera `APP_KEY` e executa as migrations. O Compose fixa `APP_ENV=production`, portanto não cria contas de demonstração.

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
# Configure APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD e as credenciais Ombala/GPO no .env.
docker-compose up --build -d
docker-compose logs -f app
```

A API fica disponível em `http://<ip-ou-dominio-da-vps>:3043` (ou na porta definida por `APP_PORT`). Abra essa porta no firewall/security group da VPS. O Compose força `APP_ENV=production` e `APP_DEBUG=false`. Não publica nem cria outro MySQL: por padrão, `host.docker.internal` aponta para a VPS, onde o container MySQL existente precisa publicar a porta `3306`. Se necessário, altere `COMPOSE_DB_HOST`. Crie no MySQL o banco e um utilizador dedicado com permissões para esse banco; evite usar `root`. Os ficheiros da aplicação persistem em volume Docker. Configure `APP_URL` e `PAYMENT_GATEWAY=gpo` no `.env`; a chave gerada é gravada nesse ficheiro.

Para parar sem apagar dados:

```powershell
docker-compose down
```

Para apagar o volume de ficheiros da aplicação (não apaga dados do MySQL externo):

```powershell
docker-compose down --volumes
```

O Compose publica HTTP na porta 3043; em produção, termine TLS num proxy reverso (por exemplo, Nginx) e restrinja o acesso direto à porta conforme necessário. Guarde o `.env` fora do controlo de versão e use uma estratégia controlada para migrations e backups.

Emails de teste:

- `admin@intermediarius.test`
- `cliente@intermediarius.test`
- `proprietario@intermediarius.test`
- `agente@intermediarius.test`

A palavra-passe vem de `DEV_SEED_PASSWORD` (valor local de exemplo: `password`). Altere-a no ambiente local se necessário e nunca reutilize esse valor em produção. Para criar um administrador fora dos dados de demonstração, use `php artisan intermediarius:create-admin {email} {name} {password}` com uma palavra-passe segura.

### Smoke test das rotas HTTP

Instale a dependência e inicie a API em `localhost`:

```bash
pip install -r requirements-api-test.txt
php artisan serve
```

Execute `python scripts/test_api.py` para testar autenticação, leituras e permissões. Para executar também as rotas que alteram dados, use `python scripts/test_api.py --write`. Para percorrer pagamentos e comissões no ambiente fake local, use `python scripts/test_api.py --write --simulate-fake-payments`. Acrescente `--keep-property` para manter o imóvel ativo e visível; essa opção não remove o imóvel nem confirma o arrendamento fake, pois a confirmação muda o estado para `rented`. O smoke test não envia SMS nem pede OTP; o fluxo de registo verificado é coberto pelos testes Feature com as chamadas Ombala simuladas.

O script recusa destinos que não sejam localhost/loopback. O modo `--write` cria registos de teste e alguns não têm rota de remoção; use uma base de dados local descartável. O modo de pagamento simulado usa um webhook fake assinado por `API_TEST_WEBHOOK_SECRET` ou `FAKE_PAYMENT_WEBHOOK_SECRET` (por omissão, `local-fake-secret`) e só está disponível em `local/testing`.

As fotos enviadas no teste são imagens JPEG reais guardadas em `tests/fixtures/property-images`, obtidas do Unsplash sob a [Unsplash License](https://unsplash.com/license); as URLs de origem estão em `tests/fixtures/property-images/SOURCES.txt`.

### Pagamentos GPO FACI

O gateway real cria referências com `POST /api/reference/` e confirma pagamentos consultando `GET /api/reference/status/`; a especificação FACI não define webhook. Configure no `.env`, sem guardar a chave no repositório:

```dotenv
PAYMENT_GATEWAY=gpo
GPO_API_BASE_URL=https://gpo.drip.infinityfree.me
GPO_API_KEY=<chave configurada no painel FACI>
GPO_API_TIMEOUT=15
GPO_SSL_VERIFY=true
```

O painel FACI precisa de uma sessão de carteira ativa. A confirmação de pagamentos pendentes é feita pelo comando `php artisan payments:sync-pending`, agendado a cada minuto pelo Laravel. Em produção, configure o cron do Scheduler:

```cron
* * * * * cd /caminho/para/a/api && php artisan schedule:run >> /dev/null 2>&1
```

Em AOA, o frontend envia `payment_method: reference`. O backend cria a referência e envia a cobrança ao telemóvel por `POST /api/requester/`, usando o nome/telemóvel do utilizador ou os dados do arrendatário no pedido de arrendamento. A confirmação é consultada pelo ID e número da referência. O teste local usa o gateway fake, não chama a GPO nem envia cobranças reais.

`GPO_SSL_VERIFY` deve permanecer `true` em produção. Para diagnóstico local, pode ser definido temporariamente como `false` se o PHP não tiver a cadeia CA necessária; o backend bloqueia essa configuração fora dos ambientes `local` e `testing`. Desactivar a verificação SSL expõe a ligação a ataques man-in-the-middle; a correcção recomendada é instalar/configurar um bundle CA válido no PHP.

### Verificação de telefone no registo (Ombala)

O registo público exige que o telefone seja verificado antes da criação da conta. Configure `OMBALA_API_TOKEN` e o remetente previamente aprovado em `OMBALA_SMS_FROM`; o token é enviado pelo backend como `Authorization: Token ...` e nunca deve ser exposto no frontend. A API converte números angolanos para o formato `9XXXXXXXX` exigido pelo Ombala.

```dotenv
OMBALA_BASE_URL=https://api.useombala.ao
OMBALA_API_TOKEN=<token do Portal Ombala>
OMBALA_SMS_FROM=<remetente aprovado no Portal Ombala>
OMBALA_TIMEOUT=15
```

Após actualizar o código, aplique a migration com `php artisan migrate`.

Fluxo:

1. `POST /api/v1/auth/phone-verifications` com `{"phone":"+244 923 000 000"}`. Responde `201` com `verification_id`, estado, tentativas restantes e validade; o OTP não é devolvido.
2. `POST /api/v1/auth/phone-verifications/verify` com `{"verification_id":"<uuid>","code":"123456"}`. A conta Ombala verifica o código; o backend responde `verified: true` apenas após confirmação do provider.
3. `POST /api/v1/auth/register` inclui `phone_verification_id` junto dos campos de registo. O backend valida que a verificação está concluída, corresponde ao telefone e ainda não foi usada antes de criar a conta e emitir o token.

O limite local é de 3 envios por telefone por hora, com intervalo de 60 segundos; os códigos têm 6 dígitos, validade de 5 minutos e até 5 tentativas. O comprovativo de verificação pode ser usado no registo durante 15 minutos e apenas uma vez.

`OMBALA_SSL_VERIFY` deve permanecer `true` em produção. `false` só é permitido em `local/testing` como workaround temporário para um bundle CA ausente; a solução recomendada é configurar o certificado CA do PHP.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
