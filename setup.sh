#!/bin/bash
set -e

# Detecta versão do docker compose disponível
if docker compose version &>/dev/null 2>&1; then
    COMPOSE="docker compose"
elif command -v docker-compose &>/dev/null 2>&1; then
    COMPOSE="docker-compose"
else
    echo "❌ docker compose / docker-compose não encontrado. Instale o Docker."
    exit 1
fi

echo "⚙️  Configurando .env..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "   .env criado a partir do .env.example"
else
    echo "   .env já existe, mantendo..."
fi

echo "🗄️  Criando banco SQLite..."
mkdir -p database
touch database/database.sqlite

echo "🐳 Buildando e subindo container..."
$COMPOSE up -d --build

echo "⏳ Aguardando container iniciar..."
sleep 5

echo "🔑 Gerando APP_KEY..."
$COMPOSE exec app php artisan key:generate --force

echo "📦 Rodando migrations..."
$COMPOSE exec app php artisan migrate --force --seed

echo ""
echo "✅ Projeto pronto em http://localhost:8181"
