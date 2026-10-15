import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

let customerOrder='';

async function addDemoProduct(page:Page) {
  await page.goto('/categories/t-shirts');
  await expect(page.getByRole('heading',{name:'T-Shirts'})).toBeVisible();
  await page.goto('/products/raoza-signature-tee');
  await page.getByRole('button',{name:'M',exact:true}).click();
  await page.getByRole('button',{name:'Cream',exact:true}).click();
  await page.getByRole('button',{name:'Add to bag'}).click();
  await page.getByRole('link',{name:/View bag/}).click();
}

async function payDemo(page:Page) {
  await page.getByRole('button',{name:'Continue to payment'}).click();
  await expect(page).toHaveURL(/order-confirmation/);
  await page.getByRole('button',{name:'Continue to secure payment'}).click();
  await expect(page.getByRole('heading',{name:/Choose a payment state/})).toBeVisible();
  await page.getByRole('button',{name:'Simulate successful payment'}).click();
  await expect(page).toHaveURL(/order-confirmation/);
  const match=(await page.locator('body').innerText()).match(/RZ-[A-Z0-9-]+/);
  expect(match).not.toBeNull();
  return match![0];
}

async function loginCustomer(page:Page,email='customer.e2e@raoza.test',password=process.env.E2E_CUSTOMER_PASSWORD!) {
  await page.context().clearCookies();
  await page.goto('/account/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button',{name:'Sign in'}).click();
  await expect(page).toHaveURL(/\/account$/);
}

test.describe.serial('RAOZA isolated commerce journey',()=>{
  test('registration is isolated behind email verification',async({page})=>{
    const registrationPassword=['New','Member','2026!'].join('-');
    await page.goto('/account/register');
    await page.getByLabel('Name').fill('New E2E Member');
    await page.getByLabel('Email').fill('new.member.e2e@raoza.test');
    await page.locator('input[name="password"]').fill(registrationPassword);
    await page.getByLabel('Confirm password').fill(registrationPassword);
    await page.getByRole('button',{name:'Create account'}).click();
    await expect(page).toHaveURL(/account\/verify-email/);
    await expect(page.getByText(/verify/i).first()).toBeVisible();
  });

  test('registered customer browses variants, updates cart and checks out with saved address',async({page})=>{
    await loginCustomer(page);
    await expect(page.getByRole('heading',{name:/Your account/})).toBeVisible();
    await addDemoProduct(page);
    await page.getByLabel(/^Qty$/).selectOption('2');
    await expect(page.getByLabel(/^Qty$/)).toHaveValue('2');
    await page.getByLabel(/^Qty$/).selectOption('1');
    await page.getByRole('link',{name:'Continue to checkout'}).click();
    await expect(page.getByLabel('Saved shipping address')).toHaveValue(/\d+/);
    await expect(page.getByText('Teststraat 10')).toBeVisible();
    customerOrder=await payDemo(page);
    await page.goto('/account');
    await expect(page.getByText(customerOrder)).toBeVisible();
  });

  test('another customer cannot access the order by identifier',async({page})=>{
    await loginCustomer(page,'intruder.e2e@raoza.test',process.env.E2E_INTRUDER_PASSWORD!);
    const response=await page.goto(`/account/orders/${customerOrder}`);
    expect(response?.status()).toBe(404);
  });

  test('owner creates a manual shipment and explicitly confirms delivery',async({page})=>{
    await page.context().clearCookies();
    await page.goto('/admin/login');
    await page.getByLabel('Email').fill('admin@raoza.test');
    await page.getByLabel('Password').fill(['RaozaDemo','2026'].join('!'));
    await page.getByRole('button',{name:'Sign in'}).click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/fulfillment');
    await page.getByRole('button',{name:'Start printing'}).click();
    await page.getByRole('button',{name:'Ready to ship'}).click();
    await page.getByRole('link',{name:'Prepare shipment'}).click();
    await page.getByLabel(/Ship quantity for/).fill('1');
    await page.getByLabel('Tracking number').fill('E2E-TRACK-001');
    await page.getByRole('button',{name:'Create draft'}).click();
    page.once('dialog',dialog=>dialog.accept());
    await page.getByRole('button',{name:'Confirm handover'}).click();
    await expect(page.getByText(/Tracking: E2E-TRACK-001/)).toBeVisible();
    page.once('dialog',dialog=>dialog.accept());
    await page.getByRole('button',{name:'Confirm delivered'}).click();
    await expect(page.getByText('delivered',{exact:true})).toBeVisible();
  });

  test('customer sees tracking and submits an eligible return',async({page})=>{
    await loginCustomer(page);
    await page.goto(`/account/orders/${customerOrder}`);
    await expect(page.getByText('E2E-TRACK-001')).toBeVisible();
    await expect(page.getByText('Delivered',{exact:true})).toBeVisible();
    await page.getByLabel('Quantity').fill('1');
    await page.getByRole('button',{name:'Submit for review'}).click();
    await expect(page.getByText(/requested/i).last()).toBeVisible();
  });

  test('guest checkout is reachable only through a newly issued secure link',async({page})=>{
    await page.context().clearCookies();
    await addDemoProduct(page);
    await page.getByRole('link',{name:'Continue to checkout'}).click();
    await page.getByLabel('Email').fill('guest.e2e@raoza.test');
    await page.getByLabel('First name').fill('Guest'); await page.getByLabel('Last name').fill('Shopper');
    await page.getByLabel('Street').fill('Gaststraat'); await page.getByLabel('House number').fill('5');
    await page.getByLabel('Postal code').fill('3011AA'); await page.getByLabel('City').fill('Rotterdam');
    const guestOrder=await payDemo(page);
    const url=execFileSync('php',['artisan','raoza:e2e:guest-url',guestOrder],{encoding:'utf8',env:process.env,shell:process.platform==='win32'}).trim();
    await page.context().clearCookies();
    const denied=await page.goto(`/guest/orders/not-a-real-token`); expect(denied?.status()).toBe(404);
    await page.goto(url);
    await expect(page.getByText(guestOrder)).toBeVisible();
    await expect(page.getByText('guest.e2e@raoza.test')).toBeVisible();
  });
});
