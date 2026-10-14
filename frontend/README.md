# frontend

TypeScript sources for the dmdune theme's scripts. `npm run build` type-checks, lints and bundles
`src/*.ts` into `blog/wp-content/themes/dmdune/assets/js/*.js` (plain browser scripts, committed,
since the server has no Node). `tools/deploy.py` refuses to deploy if the built scripts don't match.

Soundness rules (see `eslint.config.js`): strictest compiler options, no `any`, no type assertions,
no non-null `!`, no disable comments. Data from outside the code (JSON, page globals, the DOM) goes
through the runtime checks in `src/lib/`.

    cd frontend && npm ci && npm run build
