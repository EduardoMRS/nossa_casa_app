import subprocess
import sys
import platform

def run_command(command):
    """Executa um comando no terminal adaptando para o Windows se necessário."""
    is_windows = platform.system() == "Windows"
    
    print(f"\n🚀 Executando: {' '.join(command)}")
    try:
        subprocess.run(command, shell=is_windows, check=True)
    except subprocess.CalledProcessError as e:
        print(f"❌ Erro ao executar o comando: {e}")
        sys.exit(1)

def main():
    print("--- Iniciando o Deploy do Projeto ---")
    
    run_command(["git", "pull", "origin", "--no-rebase"])

    run_command(["composer", "install", "--no-interaction", "--optimize-autoloader", "--no-dev"])

    run_command(["npm", "install", "--force"])

    run_command(["npm", "run", "build"])

    print("\n⚡ Tudo instalado! O conteudo será acessado apartir de ./public")

if __name__ == "__main__":
    main()
