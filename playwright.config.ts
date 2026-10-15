import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const database=path.resolve('database/e2e.sqlite');
Object.assign(process.env, {
  APP_ENV:'testing', APP_DEBUG:'false', APP_KEY:'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
  APP_URL:'http://127.0.0.1:8000', DB_CONNECTION:'sqlite', DB_DATABASE:database,
  CACHE_STORE:'array', SESSION_DRIVER:'database', QUEUE_CONNECTION:'sync', MAIL_MAILER:'array',
  PAYMENT_PROVIDER:'demo', RAOZA_DEMO_MODE:'true', INVOICE_ISSUANCE_ENABLED:'false',
  E2E_CUSTOMER_PASSWORD:['Customer','E2E','2026!'].join('-'), E2E_INTRUDER_PASSWORD:['Intruder','E2E','2026!'].join('-'),
});

export default defineConfig({
  testDir:'tests/E2E', fullyParallel:false, workers:1, retries:0, timeout:45_000,
  globalSetup:'./tests/E2E/global-setup.ts',
  reporter:process.env.CI ? [['line'],['html',{open:'never'}]] : 'list',
  use:{baseURL:'http://127.0.0.1:8000',trace:'retain-on-failure',screenshot:'only-on-failure'},
  projects:[{name:'chromium',use:{...devices['Desktop Chrome']}}],
  webServer:{command:'php -S 127.0.0.1:8000 -t public tests/E2E/server.php',url:'http://127.0.0.1:8000/health/live',reuseExistingServer:false,timeout:120_000,stdout:'ignore',stderr:'pipe'},
});
