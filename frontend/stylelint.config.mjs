// Design token gate (design plan 7.7): colors come only from src/theme/tokens.ts through --mf-* variables.
export default {
  ignoreFiles: ['dist/**', 'node_modules/**', 'reports/**', 'src/styles/_tokens.scss'],
  overrides: [
    { files: ['**/*.vue'], customSyntax: 'postcss-html' },
    { files: ['**/*.scss'], customSyntax: 'postcss-scss' }
  ],
  rules: {
    'color-no-hex': [true, { message: 'Используйте семантический токен --mf-color-* или --mf-tone-* вместо hex.' }],
    'color-named': ['never', { message: 'Используйте семантический токен вместо именованного цвета.' }],
    'declaration-property-value-disallowed-list': [
      { '/.*/': ['/var\\(--mf-(sea|ink|pastel)-/'] },
      { message: 'Примитивы палитры доступны только в _tokens.scss, аватаре и components/viz.' }
    ]
  }
};
