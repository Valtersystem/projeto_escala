// Datas da API chegam como "AAAA-MM-DD". Usa meio-dia para o fuso do aparelho não mudar o dia.

const noMeioDia = (iso) => new Date(`${iso}T12:00:00`);

const SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

export const diaDaSemana = (iso) => SEMANA[noMeioDia(iso).getDay()];
export const diaDoMes = (iso) => iso.slice(8, 10);
export const mesPorExtenso = (mes) => `${MESES[Number(mes.slice(5, 7)) - 1]} de ${mes.slice(0, 4)}`;

/** "08 de novembro" */
export function dataCurta(iso) {
  return `${diaDoMes(iso)} de ${MESES[Number(iso.slice(5, 7)) - 1]}`;
}

/** "AAAA-MM-DD" de hoje, no fuso do aparelho. */
export function hojeIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export const horario = (inicio, fim) => `${inicio} às ${fim}`;
