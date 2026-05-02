import preset from './vendor/filament/filament/tailwind.config.preset'

/** @type {import('tailwindcss').Config} */
export default {
  presets: [preset],
  darkMode: 'class',
  content: [
    "./resources/**/**/*.blade.php",
    "./resources/**/**/*.js",
    "./app/View/Components/**/**/*.php",
    "./app/Livewire/**/**/*.php",
    "./vendor/robsontenorio/mary/src/View/Components/**/*.php",
    './app/Filament/**/*.php',
    './resources/views/filament/**/*.blade.php',
    './vendor/filament/**/*.blade.php',
    './vendor/awcodes/filament-curator/resources/views/**/*.blade.php',
    './vendor/awcodes/filament-curator/resources/**/*.blade.php',
  ],
  theme: {
    extend: {
      backgroundImage: {
      'main-gradient': "linear-gradient(135deg, rgba(255, 192, 203, 0.1) 0%, rgba(255, 240, 245, 0.2) 100%)",
    },
      colors: {
        // الألوان الجديدة المستوحاة من index.html
primary: {
  DEFAULT: '#FFB6C1', // Light Pink
  light: '#FFE4E1',   // Misty Rose
  dark: '#DB7093',    // Pale Violet Red
  50: '#FFF5F6',
  100: '#FFEBEF',
  200: '#FFD6DE',
  300: '#FFB6C1',
  400: '#FF8DA1',
  500: '#FFB6C1',
  600: '#E68A99',
  700: '#B36B77',
  800: '#804D55',
  900: '#4D2E33'
},
'accent-gradient': 'linear-gradient(135deg, #FFB6C1 0%, #FFE4E1 100%)',
      },
      fontFamily: {
        cairo: ['Cairo', 'sans-serif'],
        playfair: ['Playfair Display', 'serif'], // أضفنا خط العناوين الفاخر
      },
      animation: {
        'spin-slow': 'spin 3s linear infinite',
      }
    },
  },
// tailwind.config.js
plugins: [
    require("daisyui"),
    function({ addComponents }) {
      addComponents({
'.btn-primary': {
  'background': 'linear-gradient(135deg, #FFB6C1 0%, #FFD1DC 100%)',
  'box-shadow': '0 4px 15px rgba(255, 182, 193, 0.3)',
  'color': '#4A4A4A', // نصوص داكنة قليلاً لتناسب الزهري الفاتح
},
        '.btn-outline-custom': {
          '@apply border border-primary/30 text-neutral hover:bg-primary/5 transition-all duration-300': {},
        }


      })
    }
],
  daisyui: {
    themes: [
      {
luxury: {
  "primary": "#FFB6C1",
  "secondary": "#FFD1DC",
  "accent": "#DB7093",
  "base-100": "#FFF9FB", // خلفية تميل للزهري الأبيض
  "neutral": "#4A4A4A",
},
      },
    ],
  }
}
