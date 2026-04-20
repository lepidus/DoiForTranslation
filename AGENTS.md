# AGENTS.md

Guia para agentes trabalhando no plugin. Leia antes de tocar em código.

Plugin doiForTranslation no OJS 3.3

## Modo de trabalho

- **XP + TDD sem exceção.** Sem teste falhando, não escreve produção.
- Uma iteração por vez. Sem misturar escopo.
- Desenvolvimento em par com o humano. (voce deve ter uma SKILL pra isso)

## Comandos (sempre da raiz do OJS, dois níveis acima daqui)

### PHPUnit — testes de unidade

```bash
find  plugins/<tipo>/<nomeDoPlugin> -name tests -type d -exec php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit-env2.xml -v "{}" ";"
```

### PHP CS Fixer — antes de considerar PHP pronto

```bash
php vendor/bin/php-cs-fixer fix \
  --config .php-cs-fixer.dist.php --allow-risky=yes \
  plugins/generic/doiForTranslation/
```

PHP só está pronto quando: testes passam, e fixer rodou sem pendências.

### Cypress — sempre em lote, só o plugin

```bash
npx cypress run \
  --config 'baseUrl=http://localhost:8000,integrationFolder=plugins/{tipo}/{nomeDoPlugin}/cypress/tests,testFiles=*.spec.js' \
  --browser chrome
```
- Os testes **não são idempotentes** — rodam na ordem específica, contra banco FRESCO. **Nunca** rode um spec isolado fora dessa suíte, nem adicione specs ao `cypress.config.js` global.
- Para resetar o banco, carregue o dataset de teste da PKP na base configurada no config.inc.php. Dataset pode ser baixado em `https://raw.githubusercontent.com/pkp/datasets/refs/heads/main/ojs/stable-3_3_0/mysql/database.sql`
- O humano geralmente é o responsável por rodar o OJS com `php -S localhost:8000` em outro terminal.
