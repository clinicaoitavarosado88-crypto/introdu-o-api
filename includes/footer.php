<script>
  function carregarDia(dia) {
    const loader = document.getElementById('loader');
    loader.classList.remove('hidden');
    setTimeout(() => {
      loader.classList.add('hidden');
      alert('Dia selecionado: ' + dia.charAt(0).toUpperCase() + dia.slice(1));
    }, 1500);
  }

  function toggleDarkMode() {
    const html = document.documentElement;
    const btn = document.getElementById('toggleThemeBtn');
    html.classList.toggle('dark');
    btn.textContent = html.classList.contains('dark') ? '🌙' : '🌞';
  }
</script>
</body>
</html>
