<header class="w-full bg-[#0C9C99] dark:bg-gray-800 shadow-sm px-6 py-4 flex justify-between items-center">
  <h1 class="text-lg md:text-2xl text-white font-semibold"></h1>

  <div class="flex items-center space-x-4 text-white text-sm">
    
    <!-- Dropdown de Clínica -->
    <div class="relative hidden sm:inline-block text-white text-sm">
        <button id="clinicaBtn" class="flex items-center space-x-1 hover:opacity-90 focus:outline-none">
            <span id="clinicaAtual">
                <?php
                $cidade_id = $_GET['cidade'] ?? '1';
                $cidades = [
                    '1' => 'Clínica - Mossoró',
                    '2' => 'Clínica - Natal', 
                    '3' => 'Clínica - Parnamirim',
                    '4' => 'Clínica - Baraúna',
                    '5' => 'Clínica - Assú',
                    '8' => 'Clínica - Santo Antônio',
                    '13' => 'Clínica - Alto do Rodrigues',
                    '14' => 'Clínica - Extremoz'
                ];
                echo $cidades[$cidade_id] ?? 'Clínica - Mossoró';
                ?>
            </span>
            <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.293l3.71-4.06a.75.75 0 111.08 1.04l-4.25 4.65a.75.75 0 01-1.08 0l-4.25-4.65a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <ul id="clinicaDropdown" class="absolute right-0 mt-2 w-48 bg-white text-black rounded-md shadow-lg py-1 hidden z-50">
            <li><a href="#" onclick="trocarCidade(1, 'Clínica - Mossoró')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Mossoró</a></li>
            <li><a href="#" onclick="trocarCidade(2, 'Clínica - Natal')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Natal</a></li>
            <li><a href="#" onclick="trocarCidade(3, 'Clínica - Parnamirim')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Parnamirim</a></li>
            <li><a href="#" onclick="trocarCidade(4, 'Clínica - Baraúna')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Baraúna</a></li>
            <li><a href="#" onclick="trocarCidade(5, 'Clínica - Assú')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Assú</a></li>
            <li><a href="#" onclick="trocarCidade(8, 'Clínica - Santo Antônio')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Santo Antônio</a></li>
            <li><a href="#" onclick="trocarCidade(13, 'Clínica - Alto do Rodrigues')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Alto do Rodrigues</a></li>
            <li><a href="#" onclick="trocarCidade(14, 'Clínica - Extremoz')" class="block px-4 py-2 hover:bg-gray-100">Clínica - Extremoz</a></li>
        </ul>
    </div>

    <!-- Avatar -->
    <img src="https://i.pravatar.cc/40" alt="Avatar" class="w-10 h-10 rounded-full">

    <!-- Botão modo claro/escuro -->
    <button onclick="toggleDarkMode()" id="toggleThemeBtn" aria-label="Alternar modo"
      class="text-xl px-2 py-1 rounded transition text-white">
      🌞
    </button>
  </div>
</header>