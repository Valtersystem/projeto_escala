// Textos que se repetem em várias telas.

/** "Louvor · Som 08:00–11:30", como a API descreve o outro turno de um choque. */
export function descreverConflito(c) {
  return `${c.ministerio ?? 'Outro ministério'} · ${c.posto ?? 'turno'} ${c.inicio}–${c.fim}`;
}

/** "Culto da Manhã" se houver, senão o nome do posto. */
export function tituloDoTurno(turno) {
  return turno.culto || turno.posto?.nome || 'Turno';
}

/** "08:00 às 11:30 · Som · Banda 1" (o culto fica no título, então não repete aqui). */
export function detalheDoTurno(turno) {
  const partes = [`${turno.inicio} às ${turno.fim}`];
  if (turno.culto && turno.posto) partes.push(turno.posto.nome);
  if (!turno.culto && turno.posto) return [partes[0], ...(turno.banda ? [turno.banda.nome] : [])].join(' · ');
  if (turno.banda) partes.push(turno.banda.nome);
  return partes.join(' · ');
}

export const MOTIVOS_DE_TROCA = ['Viagem', 'Trabalho', 'Saúde', 'Compromisso de família', 'Imprevisto', 'Outro motivo'];
