/** Como cada estado da escala aparece na tela. */
export function statusDaEscala(status) {
  return {
    rascunho: { texto: 'Rascunho', tipo: 'mudo', dica: 'Ninguém vê ainda. Monte os turnos e libere para a equipe marcar.' },
    aberta: { texto: 'Recolhendo', tipo: 'marca', dica: 'A equipe já pode marcar disponibilidade.' },
    publicada: { texto: 'Publicada', tipo: 'ok', dica: 'Cada pessoa recebeu os turnos dela.' },
  }[status] ?? { texto: status, tipo: 'mudo', dica: '' };
}

/** "AAAA-MM" dos próximos N meses, a partir do mês de hoje. */
export function proximosMeses(quantidade = 8, hoje = new Date()) {
  return Array.from({ length: quantidade }, (_, i) => {
    const d = new Date(hoje.getFullYear(), hoje.getMonth() + i, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });
}

/** "MR" para "Marcos Reis". */
export function iniciaisDe(nome = '') {
  const partes = nome.trim().split(/\s+/);
  return ((partes[0]?.[0] ?? '') + (partes[1]?.[0] ?? '')).toUpperCase() || '?';
}
