#!/usr/bin/env bash
set -euo pipefail

BACKEND_URL="${BACKEND_URL:-http://localhost:18080}"
AI_URL="${AI_URL:-http://localhost:18000}"
FRONTEND_URL="${FRONTEND_URL:-http://localhost:15173}"

printf '\n[Smoke] Backend health...\n'
curl -fsS "$BACKEND_URL/api/health"
printf '\n'

printf '\n[Smoke] AI service health...\n'
curl -fsS "$AI_URL/health"
printf '\n'

printf '\n[Smoke] Embedding status...\n'
curl -fsS "$AI_URL/embeddings/status"
printf '\n'

printf '\n[Smoke] Embedding warmup...\n'
curl -fsS -X POST "$AI_URL/embeddings/warmup"
printf '\n'

printf '\n[Smoke] Login...\n'
TOKEN=$(curl -fsS -X POST "$BACKEND_URL/api/v1/session" \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['access_token'])")

if [ -z "$TOKEN" ]; then
  echo 'Failed to get token' >&2
  exit 1
fi

echo "Token acquired."

printf '\n[Smoke] Model registry...\n'
curl -fsS "$BACKEND_URL/api/v1/ai-models?per_page=5" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c "import sys,json; data=json.load(sys.stdin); print('models:', len(data['data']['data']))"

printf '\n[Smoke] RAG search endpoint...\n'
curl -fsS -X POST "$BACKEND_URL/api/v1/rag/search" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"query":"测试 RAG 检索接口是否正常","top_k":3}' \
  | python3 -c "import sys,json; data=json.load(sys.stdin); print('success:', data.get('success'), 'results:', len(data.get('data',{}).get('results',[])), 'message:', data.get('message'))"

printf '\n[Smoke] Frontend reachable...\n'
curl -fsS -I "$FRONTEND_URL" | head -n 1

printf '\n[Smoke] OK\n'
