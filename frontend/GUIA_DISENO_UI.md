# Guia de Diseno UI

## Objetivo
- Esta guia define el patron visual del frontend actual.
- Sirve para que futuras sesiones mantengan el mismo lenguaje de interfaz.
- Si una nueva pantalla no sigue estas reglas, se considera inconsistente aunque funcione.

## Direccion Visual
- Estilo general: profesional, minimalista, administrativo, sobrio.
- Base visual: blanco y negro.
- Color: usarlo solo para estados, jerarquia o enfasis puntual.
- Sensacion buscada: producto institucional moderno, limpio y serio.
- Evitar interfaces recargadas, marketing-like o decorativas.

## Regla Principal
- Cada pantalla debe verse como parte del mismo sistema, no como una landing page aislada.
- El contenido real debe tener prioridad sobre adornos visuales.
- Si hay duda entre dos opciones correctas, elegir la mas simple y sobria.

## Layout
- El navbar principal debe mantenerse flotante, con bordes redondeados y fondo translcido suave.
- El contenido principal debe vivir dentro de un ancho centrado tipo `max-w-7xl`.
- Evitar sidebars decorativas o paneles secundarios si no aportan funcionalidad real.
- El espacio en blanco debe ayudar a separar bloques, no a inflar artificialmente la pantalla.

## Superficies
- Usar `Card` como contenedor base de secciones importantes.
- Las cards deben tener:
  - borde suave
  - fondo blanco o casi blanco
  - sombra ligera
  - esquinas redondeadas amplias
- Evitar bloques planos sin borde si forman parte de una seccion principal.

## Tipografia
- Titulos: grandes, compactos, con tracking ligeramente cerrado.
- Subtitulos y descripciones: discretos, con color `muted`.
- Labels: mayusculas pequenas con tracking amplio para dar estructura.
- Evitar demasiados pesos tipograficos distintos en una misma vista.

## Color
- Fondo general: claro, casi blanco.
- Texto principal: negro o casi negro.
- Texto secundario: `muted-foreground`.
- Acentos permitidos:
  - verde: exito, habilitado, estado positivo
  - ambar: advertencia o atencion
  - sky o violet: clasificacion secundaria si realmente ayuda
- No usar multiples colores fuertes compitiendo en la misma seccion.

## Componentes
- Priorizar componentes de `shadcn` ya presentes en el proyecto.
- Antes de crear markup custom, verificar si existe un componente adecuado.
- Usar especialmente:
  - `Card`
  - `Button`
  - `Badge`
  - `Input`
  - `Alert`
  - `Separator`
  - `DropdownMenu`
- Los placeholders tambien deben verse terminados, no como texto crudo dentro de un `div`.

## Botones
- Boton principal:
  - fondo oscuro
  - texto claro
  - forma redondeada tipo pill cuando sea accion importante
- Boton secundario:
  - `outline`
  - borde suave
  - fondo claro
- No abusar de botones con color si no son acciones clave.

## Formularios
- Los formularios deben sentirse limpios y directos.
- Inputs:
  - altos
  - redondeados
  - fondo claro
  - borde suave
- Mantener una sola columna cuando no haya una razon fuerte para dividir.
- Los mensajes de error o exito deben ser claros y discretos.

## Tablas
- Las tablas son parte central del sistema.
- Deben priorizar lectura y orden, no decoracion.
- Reglas para tablas:
  - encabezado claro
  - bordes sutiles
  - buen padding horizontal y vertical
  - estados usando `Badge`
  - controles de busqueda y paginacion arriba o abajo con orden claro
- No convertir una tabla administrativa en un dashboard vistoso.

## Navbar y Navegacion
- El navbar es sobrio y compacto.
- Debe usar fondo claro, borde suave, sombra ligera y esquinas grandes.
- Las acciones visibles en navbar deben ser solo las necesarias.
- Evitar elementos decorativos o texto auxiliar que no ayuden a navegar.
- Los dropdowns deben verse refinados y consistentes con las cards.

## Paginas de Auth
- El login debe estar centrado y simplificado.
- Debe enfocarse solo en:
  - titulo
  - descripcion breve
  - campos
  - accion principal
- Quitar bloques laterales o textos promocionales si no ayudan al flujo.

## Dashboard y Pagina Principal
- La pagina principal no debe parecer un dashboard genérico de analytics.
- Debe representar trabajo operativo real.
- Si la vista principal es una tabla o modulo funcional, esa pieza debe dominar la pantalla.
- Evitar hero sections grandes si no agregan valor real.

## Placeholders y Modulos no Implementados
- Si un modulo aun no existe, mostrar un placeholder elegante dentro del sistema visual.
- No usar mensajes crudos como "En construccion" sin contexto.
- El placeholder debe parecer parte del producto final.

## Responsive
- Debe funcionar bien en desktop y mobile.
- En mobile:
  - apilar bloques verticalmente
  - evitar overflow innecesario
  - reducir ruido visual
- No meter demasiados elementos secundarios en pantallas pequenas.

## Que Evitar
- Gradientes llamativos o de estilo marketing.
- Morados, azules electricos o paletas saturadas por defecto.
- Tarjetas con demasiada informacion irrelevante.
- Paneles laterales decorativos sin funcion real.
- Texto explicativo excesivo.
- Variaciones visuales arbitrarias entre pantallas.
- Mezclar estilo institucional con estilo startup o landing page.

## Heuristica de Revision
- Antes de dar una pantalla por terminada, revisar:
  - Se ve institucional y moderna.
  - La informacion importante domina la vista.
  - El color esta usado con moderacion.
  - La pantalla se siente parte del mismo sistema.
  - No hay adornos que compitan con el contenido.

## Archivos de Referencia Actual
- `src/app/layouts/MainLayout.jsx`
- `src/modules/auth/pages/LoginPage.jsx`
- `src/modules/dashboard/pages/DashboardPage.jsx`
- `src/styles/globals.css`

## Instruccion para IA
- Al crear o redisenar una pantalla en este proyecto, seguir primero esta guia y despues el requerimiento puntual del usuario.
- No introducir un nuevo lenguaje visual sin pedido explicito.
- Si una propuesta se aleja de esta guia, ajustar el diseno antes de entregarlo.
