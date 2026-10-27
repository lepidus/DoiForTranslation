# DOI para submissão com tradução

Outros idiomas:
- [English](README.md)
- [Español](README.es.md)

## Descrição

Este plugin permite criar uma cópia de uma submissão, na qual essa nova submissão representa uma tradução da original em um determinado idioma. Além disso, ele envia ao Crossref a relação `hasTranslation`/`isTranslationOf`, vinculando o DOI do idioma original ao DOI da submissão que é uma tradução.

Por fim, ele insere links na página de cada submissão para que os usuários possam encontrar facilmente as versões em todos os idiomas disponíveis.

## Compatibilidade

Este plugin é compatível com as seguintes aplicações PKP:

- OJS 3.5.0-x

Traduções começam na etapa de submissão, sem DOI, caminho de URL, data de publicação ou edição atribuídos. A data de submissão registra a criação da tradução. A capa fica sem valor para que cada tradução use seu próprio arquivo enviado. Títulos e autores são copiados como ponto de partida; os editores devem revisar os metadados traduzidos antes da publicação.

## Instalação

Instale o plugin *DOI For Translation* pela *Galeria de Plugins* em `Painel > Website > Plugins`.

## Como usar

Com o plugin instalado e habilitado, você pode acessar uma submissão ativa, clicar na opção “Create translation” e selecionar o idioma da tradução. Uma cópia da submissão será criada, e você poderá visualizá-la em “Translations”. Na submissão traduzida, você pode adicionar arquivos da submissão no idioma correspondente.

## Testes

A suíte PHP cobre criação de traduções, autorização, links públicos e relações Crossref. A suíte Cypress em `cypress/tests` cobre o fluxo editorial e o site público no OJS 3.5 e roda na CI contra o dataset `stable-3_5_0` do `pkp/datasets`.

## Créditos

Este plugin foi desenvolvido pela [Lepidus Tecnologia](https://lepidus.com.br/) para a revista [REGEPE](https://regepe.org.br/) da Associação Nacional de Estudos em Empreendedorismo e Gestão de Pequenas Empresas ([ANEGEPE](https://anegepe.org.br/)).

## Licença
__Este plugin está licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2023-2025 Lepidus Tecnologia__
