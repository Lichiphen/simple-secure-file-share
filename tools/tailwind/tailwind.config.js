// Build: npx tailwindcss@3.4.17 -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o assets/css/tailwind.min.css --minify
module.exports = {
  content: ['./simple-file-share.php', './assets/js/app.js'],
  theme: { extend: {} },
  plugins: [],
};
