# Estimativa de custos — DRE na AWS (fora do Academy)

Região **us-east-1**, preço sob demanda, **sem free tier**, valores aproximados de nov/2026.
Conferido no AWS Pricing Calculator — ver seção no final.

## Cenário

| Premissa | Valor |
|---|---|
| Usuários/mês | 10.000 |
| Chamadas de API | 100.000 |
| Operações no banco | 500.000 |
| Armazenamento | 10 GB (uploads + backups) |
| Funcionamento | 24×7 (730 h/mês) |

## Conta

| Serviço | Cálculo | US$/mês |
|---|---|---|
| EC2 t3.small | 730 h × ~0,0208 | 15,18 |
| EBS gp3 20 GB | 20 × ~0,08 | 1,60 |
| Monitoramento detalhado EC2 | ~7 métricas × 0,30 | 2,10 |
| IPv4 público (Elastic IP) | 730 h × 0,005 | 3,65 |
| RDS db.t3.micro MySQL Single-AZ | 730 h × ~0,017 | 12,41 |
| RDS storage gp3 20 GB | 20 × ~0,115 | 2,30 |
| RDS backup (≤ tamanho do banco) | incluso | 0,00 |
| API Gateway HTTP API | ~1 M req × US$ 1/milhão + ~10 GB saída × 0,09 | ~1,90 |
| S3 backups | ~10 GB × 0,023 + requisições | ~0,30 |
| CloudWatch Logs | ~2 GB ingestão × 0,50 + armazenamento | ~1,05 |
| CloudWatch alarmes | 8 × 0,10 | 0,80 |
| CloudWatch métricas custom | 2 × 0,30 | 0,60 |
| CloudWatch dashboard | 1 (3 primeiros gratuitos) | 0,00 |
| Secrets Manager | 1 segredo × 0,40 (+ chamadas desprezíveis) | 0,40 |
| SNS e-mail | < 1.000 e-mails | 0,00 |
| **Total** | | **≈ 42–43** |

## Leitura

- **Maior custo:** a computação ligada 24×7 — EC2 (~US$ 19 com disco e monitoramento) e RDS (~US$ 15). As 100 mil chamadas de API e as 500 mil operações no banco quase não pesam: não são cobradas por requisição nessa arquitetura.
- **Produção com alta disponibilidade** (ALB + 2 EC2 + RDS Multi-AZ): ~US$ 16 do ALB + EC2 em dobro + RDS em dobro ≈ **US$ 90–100/mês**.
- **Como reduzir:** Savings Plan de 1 ano (−30% a −40%), instâncias Graviton (t4g / db.t4g, −20%), desligar HML fora do horário, retenção menor de logs.

## Conferência no AWS Pricing Calculator (05/11/2026)

Mesma arquitetura montada no AWS Pricing Calculator, us-east-1, sob demanda — **[estimativa pública](https://calculator.aws/#/estimate?id=f574f4eff1fce70d47bed9cb6b2144a2a2c7e7c9)**:

| Serviço | Configuração | US$/mês |
|---|---|---|
| Amazon EC2 | 1× t3.small Linux, uso constante | 15,18 |
| Amazon RDS for MySQL | db.t3.micro **Multi-AZ** + 20 GB gp3 | 29,42 |
| Amazon API Gateway | HTTP API, 1 milhão de requisições | 1,00 |
| Amazon S3 | 10 GB S3 Standard | 0,23 |
| AWS Secrets Manager | 2 segredos (senha do banco + segredo de origem) | 0,80 |
| Amazon VPC | 1 IPv4 público (Elastic IP) 730 h | 3,65 |
| **Total** | | **50,28/mês · 603,36 em 12 meses** |

**Diferença para a conta acima.** Na calculadora o RDS ficou **Multi-AZ** (réplica em espera em outra zona), que é a configuração de **produção** — por isso ele dobra (~29 em vez de ~15) e vira o **serviço mais caro**. No ambiente `dev` do lab o template usa Single-AZ (Multi-AZ só quando `Environment=prod`). A calculadora não inclui o CloudWatch (~3 US$) nem o disco EBS da EC2 (~1,60 US$).

**Conclusão:** entre **US$ 40 (dev, Single-AZ)** e **US$ 55 (prod, Multi-AZ + CloudWatch + EBS)** por mês. O custo é quase todo computação ligada 24×7 (EC2 + RDS); requisições e armazenamento quase não pesam.
