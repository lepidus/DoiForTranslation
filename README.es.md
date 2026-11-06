# DOI para envíos con traducción

Otros idiomas:
- [English](README.md)
- [Português do Brasil](README.pt_BR.md)

## Descripción

Este plugin permite crear una copia de un envío para que el nuevo envío represente una traducción del original en un idioma determinado. Además, envía a Crossref la relación `hasTranslation`/`isTranslationOf`, vinculando el DOI del idioma original con el DOI del envío que corresponde a la traducción.

Por último, inserta enlaces en la página de cada envío para que las personas usuarias puedan encontrar fácilmente las versiones disponibles en todos los idiomas.

## Compatibilidad

Este plugin es compatible con las siguientes aplicaciones PKP:

- OJS 3.3.0-x

Las traducciones comienzan en la etapa de envío, con una nueva fecha de envío. El DOI original y los demás identificadores, como URN e ID del editor (incluidos sus sufijos personalizados), la ruta URL, la fecha de publicación y el número asignado no se copian. El contacto principal apunta al autor copiado. Las citas se importan para la traducción y cada imagen de portada se copia a un archivo propio. Los editores deben revisar los metadatos traducidos y asignar un DOI propio antes de publicar. Si el patrón de sufijo del DOI usa `%x`, la traducción necesita un ID del editor propio antes de poder asignar el DOI.

Las traducciones creadas por versiones anteriores no se modifican: los editores deben revisar los identificadores copiados del original y sustituirlos manualmente.

## Instalación

Instale el plugin *DOI For Translation* desde la *Galería de plugins* en `Panel de control > Sitio web > Plugins`.

## Cómo usarlo

Con el plugin instalado y habilitado, puede acceder a un envío activo, hacer clic en la opción “Create translation” y seleccionar el idioma de la traducción. Se creará una copia del envío, que podrá consultar en “Translations”. En el envío traducido, puede añadir archivos en el idioma correspondiente.

## Créditos

Este plugin fue desarrollado por [Lepidus Tecnologia](https://lepidus.com.br/) para la revista [REGEPE](https://regepe.org.br/) de la Asociación Nacional de Estudios en Emprendimiento y Gestión de Pequeñas Empresas ([ANEGEPE](https://anegepe.org.br/)).

## Licencia
__Este plugin está licenciado bajo la GNU General Public License v3.0__

__Copyright (c) 2023-2025 Lepidus Tecnologia__
