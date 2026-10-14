// Soundness rules on top of strict TypeScript: no `any`, no type assertions (`as`, `<T>`), no
// non-null `!`, and no comments that silence the checker. Values from outside the code (JSON, the
// page's globals, the DOM) are narrowed with runtime checks instead.
import tseslint from 'typescript-eslint';

export default tseslint.config(
  ...tseslint.configs.strictTypeChecked,
  {
    languageOptions: { parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname } },
    rules: {
      '@typescript-eslint/consistent-type-assertions': ['error', { assertionStyle: 'never' }],
      '@typescript-eslint/no-non-null-assertion': 'error',
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/ban-ts-comment': ['error', { 'ts-expect-error': true, 'ts-ignore': true, 'ts-nocheck': true }],
      '@typescript-eslint/switch-exhaustiveness-check': 'error',
      '@typescript-eslint/strict-boolean-expressions': ['error', { allowNullableObject: true, allowString: false, allowNumber: false }],
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
    },
    linterOptions: { noInlineConfig: true, reportUnusedDisableDirectives: 'error' },
  },
);
