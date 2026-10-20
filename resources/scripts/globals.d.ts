// Static asset modules.
declare module '*.jpg';
declare module '*.jpeg';
declare module '*.png';
declare module '*.gif';
declare module '*.webp';
declare module '*.svg';
declare module '*.css';

// CSS Modules (any "*.module.css" file exports a read-only
// record of class-name keys as strings). Webpack's css-loader
// provides the runtime implementation; this declaration just
// keeps `tsc --noEmit` happy during CI type-checks.
declare module '*.module.css' {
    const classes: { readonly [key: string]: string };
    export default classes;
}

declare module '*.module.scss' {
    const classes: { readonly [key: string]: string };
    export default classes;
}

// Allow importing SVG files as URL strings (svg-url-loader).
declare module '*.svg?url' {
    const src: string;
    export default src;
}

// Explicit declarations for SVGs imported via the `@/` path alias.
// TypeScript 5.x doesn't always apply the `declare module '*.svg'`
// wildcard to path-aliased imports that resolve to a real file, so
// we list each one explicitly here to silence `tsc --noEmit`.
declare module '@/assets/images/not_found.svg' {
    const src: string;
    export default src;
}
declare module '@/assets/images/server_error.svg' {
    const src: string;
    export default src;
}
declare module '@/assets/images/server_installing.svg' {
    const src: string;
    export default src;
}
declare module '@/assets/images/server_restore.svg' {
    const src: string;
    export default src;
}
declare module '@/assets/images/pterodactyl.svg' {
    const src: string;
    export default src;
}

import 'styled-components';
import type { NookTheme } from '@/theme/types';

declare module 'styled-components' {
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    export interface DefaultTheme extends NookTheme {}
}
