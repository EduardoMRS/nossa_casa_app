import subprocess
import sys
import platform
from pathlib import Path


def ensure_project_env():
    """Cria o .env e acrescenta apenas as chaves ausentes."""
    example_path = Path('.env.example')
    env_path = Path('.env')

    if not env_path.exists():
        print("Criando .env a partir de .env.example...")
        env_path.write_text(example_path.read_text(encoding='utf-8'), encoding='utf-8')
        return

    existing_lines = env_path.read_text(encoding='utf-8').splitlines()
    existing_keys = {
        line.split('=', 1)[0]
        for line in existing_lines
        if line and not line.startswith('#') and '=' in line
    }
    missing_lines = [
        line
        for line in example_path.read_text(encoding='utf-8').splitlines()
        if line and not line.startswith('#') and '=' in line
        and line.split('=', 1)[0] not in existing_keys
    ]

    if missing_lines:
        with env_path.open('a', encoding='utf-8') as env_file:
            env_file.write('\n' + '\n'.join(missing_lines) + '\n')


def project_env_value(key):
    """LÃª uma chave simples do .env sem alterar o arquivo."""
    prefix = f'{key}='

    for line in Path('.env').read_text(encoding='utf-8').splitlines():
        if line.startswith(prefix):
            return line[len(prefix):].strip().strip('"').strip("'")

    return ''


def run_command(command):
    """Executa um comando no terminal adaptando para o Windows se necessário."""
    # No Windows, shell=True é necessário para comandos como npm e composer
    is_windows = platform.system() == "Windows"
    
    print(f"\n🚀 Executando: {' '.join(command)}")
    try:
        subprocess.run(command, shell=is_windows, check=True)
    except subprocess.CalledProcessError as e:
        print(f"❌ Erro ao executar o comando: {e}")
        sys.exit(1)

def main():
    ensure_project_env()

    print("--- Iniciando o Setup do Projeto ---")

    # 1. Instalar dependências do PHP
    run_command(["composer", "install"])

    if not project_env_value('APP_KEY'):
        run_command(["php", "artisan", "key:generate", "--no-interaction"])

    # 2. Instalar dependências do Node
    run_command(["npm", "install"])

    print("\n⚡ Tudo instalado! Iniciando os servidores em paralelo...")
    
    # 3. Rodar os servidores simultaneamente
    # Usamos o 'npx concurrently' para abrir os dois na mesma janela do terminal
    is_windows = platform.system() == "Windows"
    comando_servidores = [
        "npx", "concurrently",
        "\"php artisan serve --port=80\"",
        "\"npm run dev\""
    ]
    
    subprocess.run(comando_servidores, shell=is_windows)

if __name__ == "__main__":
    main()
