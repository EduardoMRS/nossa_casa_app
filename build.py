import subprocess
import sys
import platform

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
    print("--- Iniciando o Setup do Projeto ---")

    # 1. Instalar dependências do PHP
    run_command(["composer", "install"])

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
