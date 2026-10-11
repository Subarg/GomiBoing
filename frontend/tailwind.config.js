/** Paleta tomada del producto: uva, chamoy, mango, guayaba y tinta de tamarindo. */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      colors: {
        uva: { DEFAULT: '#4B2A7B', claro: '#EFE8F7', oscuro: '#34195C' },
        chamoy: { DEFAULT: '#D7263D', claro: '#FDE8EB' },
        mango: { DEFAULT: '#FFB627', claro: '#FFF3D6' },
        guayaba: '#FF7A8A',
        tinta: '#1E1626',
        lienzo: '#F7F5FA',
      },
      fontFamily: {
        display: ['"Baloo 2"', 'system-ui', 'sans-serif'],
        sans: ['"DM Sans"', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
