import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { resumirNomes, usePagina } from '../src/utils/listas.js';

describe('resumirNomes', () => {
  it('lista inteira quando cabe', () => {
    expect(resumirNomes(['Louvor', 'Mídia'])).toBe('Louvor · Mídia');
    expect(resumirNomes(['Louvor'])).toBe('Louvor');
  });

  it('corta no máximo e diz quantos ficaram de fora', () => {
    expect(resumirNomes(['A', 'B', 'C', 'D', 'E'])).toBe('A · B · +3');
    expect(resumirNomes(['A', 'B', 'C'], 2)).toBe('A · B · +1');
    expect(resumirNomes(['A', 'B', 'C'], 3)).toBe('A · B · C');
  });

  it('lista vazia devolve o texto indicado (ou vazio)', () => {
    expect(resumirNomes([], 2, 'Sem ministério')).toBe('Sem ministério');
    expect(resumirNomes([])).toBe('');
  });
});

describe('usePagina', () => {
  const itens = ref(Array.from({ length: 70 }, (_, i) => i));

  it('mostra o primeiro bloco, conta o que falta e avança de bloco em bloco', () => {
    const p = usePagina(itens, 30);

    expect(p.visiveis.value).toHaveLength(30);
    expect(p.restantes.value).toBe(40);

    p.mais();
    expect(p.visiveis.value).toHaveLength(60);
    expect(p.restantes.value).toBe(10);

    p.mais();
    expect(p.visiveis.value).toHaveLength(70);
    expect(p.restantes.value).toBe(0); // nunca negativo
  });

  it('reiniciar volta ao primeiro bloco', () => {
    const p = usePagina(itens, 30);
    p.mais();
    p.reiniciar();

    expect(p.visiveis.value).toHaveLength(30);
  });

  it('acompanha a lista quando ela muda', () => {
    const lista = ref([1, 2, 3]);
    const p = usePagina(lista, 2);

    expect(p.visiveis.value).toEqual([1, 2]);
    lista.value = [9];
    expect(p.visiveis.value).toEqual([9]);
    expect(p.restantes.value).toBe(0);
  });
});
