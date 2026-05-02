#!/bin/bash
set -e

echo "🐳 Subindo container..."
docker compose up -d --build

echo "⚙️  Configurando .env..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "   .env criado a partir do .env.example"
else
    echo "   .env já existe, mantendo..."
fi

echo "🔑 Gerando APP_KEY..."
docker exec nota-api php artisan key:generate --force

echo "🗄️  Criando banco SQLite..."
touch database/database.sqlite

echo "📦 Rodando migrations..."
docker exec nota-api php artisan migrate --force --seed

echo ""
echo "✅ Projeto pronto em http://localhost:8181"
