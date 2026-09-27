export default function Money({ amount, prefix = '' }: { amount: number; prefix?: string }) {
  const value = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount / 100);
  return <>{prefix}{value}</>;
}
