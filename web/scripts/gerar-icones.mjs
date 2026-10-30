// Gera os PNGs do app a partir de public/icone.svg. Rode: npm run icones
import sharp from 'sharp';
import { readFile } from 'node:fs/promises';

const svg = await readFile(new URL('../public/icone.svg', import.meta.url));
const saida = (nome) => new URL(`../public/${nome}`, import.meta.url).pathname;

for (const [nome, tamanho] of [['icone-192.png', 192], ['icone-512.png', 512], ['apple-touch-icon.png', 180]]) {
  await sharp(svg).resize(tamanho, tamanho).png().toFile(saida(nome));
}

// Ícone "maskable": o desenho ocupa só a área segura (80%) sobre fundo cheio.
const fundo = await sharp({ create: { width: 512, height: 512, channels: 4, background: '#4A3C8C' } }).png().toBuffer();
const miolo = await sharp(svg).resize(410, 410).png().toBuffer();
await sharp(fundo).composite([{ input: miolo, gravity: 'center' }]).png().toFile(saida('icone-maskable-512.png'));

console.log('Ícones gerados em public/.');
