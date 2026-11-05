// Teste de carga (k6) — dois cenários:
//   carga    (padrão): uso realista, até 30 usuários simultâneos (~90 req/s). TEM que passar nas metas.
//   estresse         : sobe até 100 usuários (~300 req/s) pra achar o limite. Não reprova; só mede.
//
//   k6 run -e BASE_URL=https://<id>.execute-api.us-east-1.amazonaws.com -e CENARIO=carga tests/load/k6.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8080';
const CENARIO = __ENV.CENARIO || 'carga';

// separa "barrado pelo limite do API Gateway" (429) de "servidor falhou" (5xx)
const limitadas = new Counter('respostas_429_throttling');
const erros5xx = new Counter('respostas_5xx_servidor');

const cenarios = {
  carga: {
    stages: [
      { duration: '30s', target: 10 },
      { duration: '1m30s', target: 30 },
      { duration: '30s', target: 0 },
    ],
    thresholds: {
      http_req_failed: ['rate<0.01'],          // < 1% de erro
      http_req_duration: ['p(95)<800'],        // 95% abaixo de 800 ms
      'http_req_duration{nome:health}': ['p(95)<500'],
    },
  },
  estresse: {
    stages: [
      { duration: '30s', target: 20 },
      { duration: '1m', target: 50 },
      { duration: '1m', target: 100 },
      { duration: '30s', target: 0 },
    ],
    thresholds: {},                            // só observa onde quebra
  },
};

export const options = cenarios[CENARIO];

function medir(r) {
  if (r.status === 429) limitadas.add(1);
  if (r.status >= 500) erros5xx.add(1);
  return r;
}

export default function () {
  const h = medir(http.get(`${BASE}/health`, { tags: { nome: 'health' } }));
  check(h, { 'health 200': (r) => r.status === 200 });

  const l = medir(http.get(`${BASE}/login`, { tags: { nome: 'login' } }));
  check(l, { 'login 200': (r) => r.status === 200 });

  const a = medir(http.get(`${BASE}/assets/css/app.css`, { tags: { nome: 'asset' } }));
  check(a, { 'css 200': (r) => r.status === 200 });

  sleep(1);
}
