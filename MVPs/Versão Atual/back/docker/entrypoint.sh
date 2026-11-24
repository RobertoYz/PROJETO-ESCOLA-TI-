#!/usr/bin/env bash
set -e

cd /var/www/html

echo "=================================================="
echo " [Anjo Nexus] Inicializando container Back-end..."
echo "=================================================="

# 1. Garante estrutura de pastas de runtime do Laravel (com fallback seguro em bind-mount do Windows)
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public/kanban_anexos \
         bootstrap/cache || true

chmod -R 775 storage bootstrap/cache >/dev/null 2>&1 || true

# 2. Se vendor/autoload.php nao existir, instala dependencias do Composer
if [ ! -f "vendor/autoload.php" ]; then
    echo "-> [Composer] Pasta vendor/ ausente. Executando composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 3. Se .env nao existir, cria a partir do .env.example
if [ ! -f ".env" ]; then
    echo "-> [Env] Arquivo .env nao encontrado. Copiando de .env.example..."
    cp .env.example .env || true
fi

# 4. Garante que APP_KEY esteja preenchida
APP_KEY_ATUAL=$(grep -E '^APP_KEY=' .env 2>/dev/null | cut -d '=' -f2- | tr -d '[:space:]' || true)
if [ -z "${APP_KEY}" ] && [ -z "${APP_KEY_ATUAL}" ]; then
    echo "-> [Key] Gerando APP_KEY da aplicacao..."
    php artisan key:generate --force --no-interaction || true
fi

# 5. Aguarda o servico MySQL (db) aceitar conexoes PDO antes de rodar comandos Artisan
DB_HOST_TARGET="${DB_HOST:-db}"
DB_PORT_TARGET="${DB_PORT:-3306}"
DB_DATABASE_TARGET="${DB_DATABASE:-anjonexus}"
DB_USERNAME_TARGET="${DB_USERNAME:-root}"
DB_PASSWORD_TARGET="${DB_PASSWORD:-root}"

echo "-> [MySQL] Aguardando banco de dados em ${DB_HOST_TARGET}:${DB_PORT_TARGET} (${DB_DATABASE_TARGET})..."
MAX_TENTATIVAS=60
TENTATIVA=1

until php -r '
    try {
        $host = getenv("DB_HOST") ?: "db";
        $port = getenv("DB_PORT") ?: "3306";
        $db   = getenv("DB_DATABASE") ?: "anjonexus";
        $user = getenv("DB_USERNAME") ?: "root";
        $pass = getenv("DB_PASSWORD") !== false ? getenv("DB_PASSWORD") : "root";
        new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' >/dev/null 2>&1; do
    if [ "${TENTATIVA}" -ge "${MAX_TENTATIVAS}" ]; then
        echo "✖ [MySQL] Tempo limite esgotado aguardando conexao com o banco de dados."
        exit 1
    fi
    echo "   ... aguardando MySQL (${TENTATIVA}/${MAX_TENTATIVAS})..."
    TENTATIVA=$((TENTATIVA + 1))
    sleep 2
done

echo "✔ [MySQL] Conexao estabelecida com sucesso!"

# 6. Se um comando customizado foi passado ao container (ex: scheduler ou spiders), executa-o diretamente
if [ "$#" -gt 0 ]; then
    echo "-> [Exec] Executando comando customizado: $*"
    exec "$@"
fi

# 7. Fluxo principal do container 'back' (API): Migrations + Seeders
FRESH_DB="${DOCKER_FRESH_DB:-true}"
if [ "${FRESH_DB}" = "true" ] || [ "${FRESH_DB}" = "1" ]; then
    echo "-> [Database] DOCKER_FRESH_DB=true: Executando migrate:fresh --seed --force..."
    php artisan migrate:fresh --seed --force --no-interaction
else
    echo "-> [Database] DOCKER_FRESH_DB=false: Executando migrate --force e seeders..."
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction
fi

# 8. Recria link simbolico public/storage com fallback seguro para volumes Windows e limpa caches
rm -rf public/storage >/dev/null 2>&1 || true
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize:clear || true

echo "=================================================="
echo " 🚀 [Anjo Nexus API] Servidor pronto em 0.0.0.0:8000"
echo "=================================================="

cd /var/www/html/public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
