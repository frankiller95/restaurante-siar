// Configuración de Quasar CLI (Vite)
// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file

import { defineConfig } from '#q-app'

export default defineConfig(() => {
  return {
    // Archivos de arranque (src/boot)
    boot: ['axios'],

    css: ['app.scss'],

    extras: ['roboto-font', 'material-icons'],

    build: {
      target: {},

      // 'history' para que el enlace de recuperación de contraseña
      // (/restablecer-contrasena?token=...) funcione sin "#".
      vueRouterMode: 'history',
    },

    devServer: {
      port: 9000,
      open: true,
    },

    framework: {
      config: {
        notify: { position: 'top-right', timeout: 3500 },
        loadingBar: { color: 'primary' },
      },

      lang: 'es',
      iconSet: 'material-icons',

      plugins: ['Notify', 'Dialog', 'Loading', 'LoadingBar'],
    },

    animations: [],
  }
})
