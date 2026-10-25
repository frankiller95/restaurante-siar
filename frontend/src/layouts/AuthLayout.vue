<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="auth-fondo flex flex-center q-pa-md">
        <div class="column items-center full-width">
          <div class="text-center text-white q-mb-lg">
            <q-icon name="restaurant" size="48px" />
            <div class="text-h4 text-weight-bold q-mt-sm">SIAR</div>
            <div class="text-subtitle2" style="opacity: 0.85">
              Sistema Integral de Administración para Restaurantes
            </div>
          </div>
          <q-card class="auth-card" flat bordered>
            <router-view />
          </q-card>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>
