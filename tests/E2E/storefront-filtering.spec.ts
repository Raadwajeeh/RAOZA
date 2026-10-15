import { expect, test, type Locator, type Page } from '@playwright/test';

function prices(cards:Locator) {
  return cards.locator('p').filter({hasText:/€\s?\d/}).allTextContents().then(values=>values.map(value=>Number(value.replace(/[^0-9,]/g,'').replace(',','.'))));
}

async function chooseDesktopFilter(page:Page,label:string,value:string) {
  const filters=page.locator('.raoza-desktop-filters');
  const checkbox=filters.getByRole('group',{name:label}).getByRole('checkbox',{name:value,exact:true});
  await checkbox.check();
  await expect(checkbox).toBeChecked();
}

async function keepNecessaryCookies(page:Page) {
  const button=page.getByRole('button',{name:'Necessary only'});
  if(await button.isVisible()) await button.click();
}

test.describe('RAOZA storefront discovery',()=>{
  test('desktop filters, sorting and clear state are URL-addressable',async({page})=>{
    await page.goto('/shop');
    await keepNecessaryCookies(page);
    const cards=page.locator('.raoza-catalog-grid article');
    await expect(cards).toHaveCount(6);

    await chooseDesktopFilter(page,'Size','M');
    await chooseDesktopFilter(page,'Color','Cream');
    await page.locator('.raoza-desktop-filters').getByRole('button',{name:'Apply filters'}).click();
    await expect(page).toHaveURL(/size%5B0%5D=M/);
    await expect(page).toHaveURL(/color%5B0%5D=Cream/);
    await expect(cards.first()).toBeVisible();

    await page.getByLabel('Sort products').selectOption('price_desc');
    await expect(page).toHaveURL(/sort=price_desc/);
    const descending=await prices(cards);
    expect(descending).toEqual([...descending].sort((a,b)=>b-a));

    await page.getByRole('button',{name:'Clear all'}).click();
    await expect(page).toHaveURL(/\/shop$/);
    await expect(cards).toHaveCount(6);
    await page.goto('/shop');
    await page.locator('footer').scrollIntoViewIfNeeded();
    await page.getByRole('heading',{name:'Shop'}).scrollIntoViewIfNeeded();
    await page.screenshot({path:'output/playwright/phase7-storefront-desktop.png',fullPage:true});
  });

  test('mobile filter drawer and variant-to-cart flow work at a narrow viewport',async({page})=>{
    await page.setViewportSize({width:390,height:844});
    await page.goto('/shop');
    await keepNecessaryCookies(page);
    await page.getByRole('button',{name:'Filters'}).click();
    const dialog=page.getByRole('dialog',{name:'Filter the edit'});
    await expect(dialog).toBeVisible();
    await dialog.getByRole('group',{name:'Size'}).getByRole('checkbox',{name:'M',exact:true}).check();
    await dialog.getByRole('group',{name:'Color'}).getByRole('checkbox',{name:'Cream',exact:true}).check();
    await dialog.getByRole('button',{name:'Apply filters'}).click();
    await expect(dialog).not.toBeVisible();
    await expect(page).toHaveURL(/size%5B0%5D=M/);
    await expect(page.locator('.raoza-catalog-grid article').first()).toBeVisible();

    await page.getByRole('link',{name:'View RAOZA Signature Tee'}).click();
    await page.getByRole('button',{name:'M',exact:true}).click();
    await page.getByRole('button',{name:'Cream',exact:true}).click();
    await page.getByRole('button',{name:'Add to bag'}).click();
    await expect(page.getByRole('link',{name:/View bag/})).toBeVisible();
    await page.getByRole('heading',{name:'RAOZA Signature Tee'}).click();
    await page.screenshot({path:'output/playwright/phase7-product-mobile.png',fullPage:true});
  });
});
