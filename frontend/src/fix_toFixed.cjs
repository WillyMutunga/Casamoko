const fs = require('fs');

const path = 'c:/Users/hp/Desktop/Casamoko/frontend/src/App.tsx';
let content = fs.readFileSync(path, 'utf8');

const replacements = [
  ['clientAccount.wallet_balance.toFixed(', 'Number(clientAccount.wallet_balance).toFixed('],
  ['resellerAccount.api_credits.toFixed(', 'Number(resellerAccount.api_credits).toFixed('],
  ['c.wallet_balance.toFixed(', 'Number(c.wallet_balance).toFixed('],
  ['c.credit_limit.toFixed(', 'Number(c.credit_limit).toFixed('],
  ['r.markup_percentage.toFixed(', 'Number(r.markup_percentage).toFixed('],
  ['r.api_credits.toFixed(', 'Number(r.api_credits).toFixed('],
  ['clientAccount.credit_limit.toFixed(', 'Number(clientAccount.credit_limit).toFixed('],
  ['t.amount.toFixed(', 'Number(t.amount).toFixed('],
  ['t.balance_after.toFixed(', 'Number(t.balance_after).toFixed('],
  ['inv.amount.toFixed(', 'Number(inv.amount).toFixed('],
  ['res.data.analysis.estimated_cost.toFixed(', 'Number(res.data.analysis.estimated_cost).toFixed(']
];

replacements.forEach(([search, replace]) => {
  content = content.split(search).join(replace);
});

fs.writeFileSync(path, content, 'utf8');
console.log('Fixed toFixed calls in App.tsx');
