// Teste de carga (k6):  k6 run -e BASE_URL=https://xxxx.cloudfront.net tests/load/k6.js
// Simula usuários abrindo a tela de login, a API e o health check.
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://localhost:8080';

export const options = {
  stages: [
    { duration: '30s', target: 20 },   // aquecimento
    { duration: '1m',  target: 50 },   // carga normal
    { duration: '1m',  target: 100 },  // pico
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed:   ['rate<0.01'],          // < 1% de erro
    http_req_duration: ['p(95)<800'],          // 95% abaixo de 800 ms
    'http_req_duration{nome:health}': ['p(95)<300'],
  },
};

export default function () {
  const h = http.get(`${BASE}/health`, { tags: { nome: 'health' } });
  check(h, { 'health 200': (r) => r.status === 200, 'health UP': (r) => r.json('status') === 'UP' });

  const l = http.get(`${BASE}/login`, { tags: { nome: 'login' } });
  check(l, { 'login 200': (r) => r.status === 200 });

  const a = http.get(`${BASE}/assets/css/app.css`, { tags: { nome: 'asset' } });
  check(a, { 'css 200': (r) => r.status === 200 });

  sleep(1);
}
