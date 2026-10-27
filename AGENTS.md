# AGENTS.md

Guia para agentes trabalhando no plugin. Leia antes de tocar em código.

Plugin doiForTranslation 2.x no OJS 3.5

## Modo de trabalho

- **XP + TDD sem exceção.** Sem teste falhando, não escreve produção.
- Uma iteração por vez. Sem misturar escopo.
- Desenvolvimento em par com o humano. (voce deve ter uma SKILL pra isso)

## Comandos (sempre da raiz do OJS, três níveis acima daqui)

### PHPUnit — testes de unidade

```bash
php lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage plugins/generic/doiForTranslation/tests
```

### PHP CS Fixer — antes de considerar PHP pronto

```bash
php lib/pkp/lib/vendor/bin/php-cs-fixer fix --rules=@PSR12 --using-cache=no plugins/generic/doiForTranslation/
```

PHP só está pronto quando: testes passam, e fixer rodou sem pendências.

### Interface e Cypress

O build e os testes da extensão Vue rodam no diretório do plugin:

```bash
npm ci
npm run build
node tests/workflowHeader.test.mjs
```

A suíte em `cypress/tests` (`*.cy.js`) cobre a interface do OJS 3.5 e roda na CI pelo template `ojs/cypress_tests.yml`. Não adicione specs do plugin à configuração global.

Os specs dependem uns dos outros: execute todos em ordem contra banco de teste fresco, com o dataset de `pkp/datasets` da linha `stable-3_5_0`, nunca o dataset 3.3. Restaurar o banco é uma operação destrutiva e exige confirmação do humano.

Traduções copiam o título do original, então o painel mostra linhas com o mesmo título até o título ser traduzido. Para abrir o original, use `cy.openSubmissionInLocale(título, 'en')` em vez de buscar pela tabela.

O humano geralmente executa o OJS com `php -S localhost:8000` em outro terminal.
