# DOI para submissão com tradução

Outros idiomas:
- [English](README.md)
- [Español](README.es.md)

## Descrição

Este plugin permite criar uma cópia de uma submissão, na qual essa nova submissão representa uma tradução da original em um determinado idioma. Além disso, ele envia ao Crossref a relação `hasTranslation`/`isTranslationOf`, vinculando o DOI do idioma original ao DOI da submissão que é uma tradução.

Por fim, ele insere links na página de cada submissão para que os usuários possam encontrar facilmente as versões em todos os idiomas disponíveis.

## Compatibilidade

Este plugin é compatível com as seguintes aplicações PKP:

- OJS 3.3.0-x

Traduções começam na etapa de submissão, com uma nova data de submissão. O DOI original e os demais identificadores, como URN e ID do editor (incluindo os sufixos personalizados), caminho de URL, data de publicação e edição atribuída não são copiados. O contato principal aponta para o autor copiado. As citações são importadas para a tradução e cada imagem de capa é copiada para um arquivo próprio. Os editores devem revisar os metadados traduzidos e atribuir um DOI próprio antes de publicar. Se o padrão de sufixo do DOI usar `%x`, a tradução precisa de um ID do editor próprio antes que o DOI possa ser atribuído.

Traduções criadas por versões anteriores não são alteradas: os editores devem revisar os identificadores copiados da original e substituí-los manualmente.

## Instalação

Instale o plugin *DOI For Translation* pela *Galeria de Plugins* em `Painel > Website > Plugins`.

## Como usar

Com o plugin instalado e habilitado, você pode acessar uma submissão ativa, clicar na opção “Create translation” e selecionar o idioma da tradução. Uma cópia da submissão será criada, e você poderá visualizá-la em “Translations”. Na submissão traduzida, você pode adicionar arquivos da submissão no idioma correspondente.

## Créditos

Este plugin foi desenvolvido pela [Lepidus Tecnologia](https://lepidus.com.br/) para a revista [REGEPE](https://regepe.org.br/) da Associação Nacional de Estudos em Empreendedorismo e Gestão de Pequenas Empresas ([ANEGEPE](https://anegepe.org.br/)).

## Licença
__Este plugin está licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2023-2025 Lepidus Tecnologia__
