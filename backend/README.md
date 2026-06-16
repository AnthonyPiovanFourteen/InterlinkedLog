# InterlinkedLog — Backend

Laravel 11 + PHP 8.4 + SQLite | Hexagonal Architecture + DDD

## Endpoints

Ver documentação completa em `docs/APRESENTACAO.md`.

## Como Rodar

```bash
composer install
cp .env.example .env
echo "DB_CONNECTION=sqlite" >> .env
touch database/database.sqlite
php artisan migrate:fresh --force
php artisan app:seed
php -S 127.0.0.1:8000 -t public
```

## Testes

```bash
./test-all.sh    # 30 cenários automatizados (raiz do projeto)
```

## Seed

```bash
php artisan app:seed
```

Credenciais padrão:
- admin@interlinked.io / admin123 (Admin)
- marina@interlinked.io / admin123 (Usuário)
- rafael@interlinked.io / admin123 (Usuário)
