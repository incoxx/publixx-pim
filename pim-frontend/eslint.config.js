import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import vueI18n from '@intlify/eslint-plugin-vue-i18n';

export default [
  js.configs.recommended,
  ...pluginVue.configs['flat/essential'],
  ...vueI18n.configs['flat/recommended'],
  {
    files: ['src/**/*.vue', 'src/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        localStorage: 'readonly',
        fetch: 'readonly',
        navigator: 'readonly',
      },
    },
    rules: {
      // Kernregel fuer die Bestandsaufnahme: findet jeden hartkodierten Text-Node
      // und jedes hartkodierte String-Attribut (title, placeholder, alt, aria-label, ...).
      '@intlify/vue-i18n/no-raw-text': ['warn', {
        attributes: {
          '/.+/': ['title', 'placeholder', 'alt', 'aria-label', 'aria-placeholder', 'label'],
        },
      }],
      '@intlify/vue-i18n/no-html-messages': 'warn',
      // Diese Regeln brauchen echte de.json/en.json-Dateien (nicht vorhanden, main.js-Inline-Objekt) -> aus.
      '@intlify/vue-i18n/no-missing-keys': 'off',
      '@intlify/vue-i18n/no-unused-keys': 'off',
      // Rauschen aus den Basis-Vue-Regeln fuer diesen Zweck abschalten, nicht Ziel dieser Analyse.
      'vue/multi-word-component-names': 'off',
      'no-unused-vars': 'warn',
    },
  },
  {
    // vitest.config.js hat "globals: true" -- describe/it/expect/vi etc. sind
    // zur Laufzeit ohne Import verfuegbar. ESLint weiss das nicht von selbst.
    files: ['src/**/__tests__/**/*.js', 'src/**/*.test.js'],
    languageOptions: {
      globals: {
        describe: 'readonly',
        it: 'readonly',
        test: 'readonly',
        expect: 'readonly',
        vi: 'readonly',
        beforeEach: 'readonly',
        afterEach: 'readonly',
        beforeAll: 'readonly',
        afterAll: 'readonly',
        suite: 'readonly',
      },
    },
  },
  {
    ignores: ['dist/**', 'node_modules/**'],
  },
];
