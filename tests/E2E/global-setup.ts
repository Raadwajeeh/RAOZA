import { execFileSync } from 'node:child_process';
import { closeSync, existsSync, openSync, unlinkSync } from 'node:fs';

export default async function globalSetup() {
  const database=process.env.DB_DATABASE!;
  if (existsSync(database)) unlinkSync(database);
  closeSync(openSync(database,'w'));
  const options={stdio:'inherit' as const,env:process.env,shell:process.platform==='win32'};
  execFileSync('php',['artisan','migrate:fresh','--force'],options);
  execFileSync('php',['artisan','db:seed','--class=Database\\Seeders\\E2eSeeder','--force'],options);
}
