<?php

// Identifica o sistema operacional
$os = strtoupper(substr(PHP_OS, 0, 3));

// Bloco de código que será inserido nos terminais Unix (Linux/macOS)
$unix_block = "\n# <<< LARAVEL ARTISAN AUTOCOMPLETE >>>\n".
"artisan() {\n".
"    if [ -f \"artisan\" ]; then\n".
"        php artisan \"\$@\"\n".
"    else\n".
"        echo \"Arquivo 'artisan' nao encontrado no diretorio atual.\"\n".
"    fi\n".
"}\n".
"if command -v php &> /dev/null && [ -f \"artisan\" ]; then\n".
"    eval \"\$(php artisan completion \$(basename \$SHELL))\"\n".
"fi\n".
"# <<< END LARAVEL ARTISAN AUTOCOMPLETE >>>\n";

// Bloco de código que será inserido no PowerShell (Windows)
$windows_block = "\r\n# <<< LARAVEL ARTISAN AUTOCOMPLETE >>>\r\n".
"function artisan {\r\n".
"    if (Test-Path \".\\artisan\") {\r\n".
"        php artisan \$args\r\n".
"    } else {\r\n".
"        Write-Host \"Arquivo 'artisan' nao encontrado no diretorio atual.\" -ForegroundColor Red\r\n".
"    }\r\n".
"}\r\n".
"Set-PSReadLineKeyHandler -Key Tab -Function MenuComplete\r\n".
"# <<< END LARAVEL ARTISAN AUTOCOMPLETE >>>\r\n";

// Captura o argumento do terminal (--install ou --uninstall)
$action = $argv[1] ?? null;

if ($action !== '--install' && $action !== '--uninstall') {
    exit("Use: php gerenciar_artisan.php [--install | --uninstall]\n");
}

// Descobre o caminho dos arquivos de configuração baseado no OS
$target_files = [];

if ($os === 'WIN') {
    // No Windows, busca o perfil padrão do PowerShell do usuário atual
    $home = getenv('USERPROFILE');
    $target_files[] = $home.'\\Documents\\PowerShell\\Microsoft.PowerShell_profile.ps1';
    $target_files[] = $home.'\\Documents\\WindowsPowerShell\\Microsoft.PowerShell_profile.ps1';
    $block_to_use = $windows_block;
} else {
    // No Linux/macOS, tenta atualizar tanto o .bashrc quanto o .zshrc se existirem
    $home = getenv('HOME');
    if (file_exists("$home/.bashrc") || $os === 'LIN') {
        $target_files[] = "$home/.bashrc";
    }
    if (file_exists("$home/.zshrc") || $os === 'DAR') {
        $target_files[] = "$home/.zshrc";
    } // DAR = Darwin (macOS)
    $block_to_use = $unix_block;
}

foreach ($target_files as $file) {
    // Garante que o diretório pai existe (útil para o PowerShell no Windows)
    $dir = dirname($file);
    if (! is_dir($dir) && $action === '--install') {
        mkdir($dir, 0755, true);
    }

    $content = file_exists($file) ? file_get_contents($file) : '';

    if ($action === '--install') {
        // Verifica se o bloco já não foi adicionado antes para evitar duplicidade
        if (strpos($content, '# <<< LARAVEL ARTISAN AUTOCOMPLETE >>>') === false) {
            file_put_contents($file, $content.$block_to_use);
            echo "Instalado com sucesso em: $file\n";
        } else {
            echo "Ja estava instalado em: $file\n";
        }
    } elseif ($action === '--uninstall') {
        if (strpos($content, '# <<< LARAVEL ARTISAN AUTOCOMPLETE >>>') !== false) {
            // Remove o bloco inteiro usando expressão regular
            $pattern = '/# <<< LARAVEL ARTISAN AUTOCOMPLETE >>>.*?# <<< END LARAVEL ARTISAN AUTOCOMPLETE >>>/s';
            $clean_content = preg_replace($pattern, '', $content);
            file_put_contents($file, trim($clean_content)."\n");
            echo "Removido com sucesso de: $file\n";
        } else {
            echo "Nenhuma configuracao encontrada em: $file\n";
        }
    }
}

echo "Procedimento concluido! Reinicie o seu terminal para aplicar as mudancas.\n";
