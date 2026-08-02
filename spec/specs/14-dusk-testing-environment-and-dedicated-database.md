# **14. Ambiente de Testes Laravel Dusk e Banco de Dados MySQL Dedicado**

---

## **1. Visão Geral & Requisitos**

* **RF30:** Isolamento estrito do banco de desenvolvimento (`plataforma_ead`) durante execuções de testes E2E do Laravel Dusk.
* **RN13:** As suítes do Laravel Dusk devem rodar obrigatoriamente apontando para um banco de dados MySQL dedicado e isolado (`testing`), gerenciado e limpo exclusivamente pelos testes Dusk (via `DatabaseMigrations` / `DatabaseTruncation`). A base de desenvolvimento (`plataforma_ead`) jamais deve ser migrada, truncada ou apagada durante a execução da suíte Dusk.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.

O objetivo desta especificação é reanalisar e reestruturar o ambiente de testes do **Laravel Dusk** no projeto. Anteriormente, execuções do Dusk podiam utilizar a conexão MySQL padrão do arquivo `.env` principal, resultando em redefinições inadvertidas do banco de desenvolvimento local (`plataforma_ead`). Com esta atualização, o Dusk passa a utilizar um arquivo `.env.dusk.local` dedicado com a base `testing` (já suportada nativamente pelo container MySQL do Laravel Sail).

---

## **2. Configuração de Ambiente & Isolamento de Banco de Dados**

### **2.1. Arquivo de Ambiente `.env.dusk.local`**

O repositório deve conter o arquivo `.env.dusk.local` configurado especificamente para o ciclo de vida do Laravel Dusk:

```ini
APP_NAME="Plataforma EAD (Dusk E2E)"
APP_ENV=dusk
APP_KEY=base64:kfLeesLwsdH3CDfQmyaBM7JBqg4ve02+ciNOHOC604c=
APP_DEBUG=true
APP_URL=http://laravel.test

DUSK_DRIVER_URL=http://selenium:4444/wd/hub

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=testing
DB_USERNAME=sail
DB_PASSWORD=password

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=sync
CACHE_STORE=database
```

### **2.2. Provisionamento do Banco no Laravel Sail (`compose.yaml`)**

O Laravel Sail provê a criação automática do banco `testing` através do script montado em `/docker-entrypoint-initdb.d/10-create-testing-database.sh` no container `mysql`:

```yaml
mysql:
    image: 'mysql:8.4'
    environment:
        MYSQL_DATABASE: '${DB_DATABASE}'
        MYSQL_USER: '${DB_USERNAME}'
        MYSQL_PASSWORD: '${DB_PASSWORD}'
    volumes:
        - 'sail-mysql:/var/lib/mysql'
        - './vendor/laravel/sail/database/mysql/create-testing-database.sh:/docker-entrypoint-initdb.d/10-create-testing-database.sh'
```

Ao subir o ambiente Sail (`vendor/bin/sail up -d`), ambos os bancos (`plataforma_ead` e `testing`) ficam disponíveis no mesmo serviço MySQL.

---

## **3. Ciclo de Vida dos Dados e Integração CI/CD**

### **3.1. Suíte de Testes Dusk (`DuskTestCase.php`)**

1. Ao executar `vendor/bin/sail dusk`, o Laravel Dusk substitui temporariamente o `.env` pelo `.env.dusk.local`.
2. As classes em `tests/Browser/*` utilizam a trait `Illuminate\Foundation\Testing\DatabaseMigrations` ou `DatabaseTruncation`.
3. As migrações e limpezas ocorrem **estritamente na base `testing`**, preservando intactas todas as tabelas e registros do banco `plataforma_ead`.

### **3.2. Esteira CI/CD (`GitHub Actions` e `.env.ci`)**

1. O arquivo `.env.ci` e o workflow `.github/workflows/ci.yml` são atualizados para alinhar a configuração de testes E2E com o banco dedicado `testing`.
2. No GitHub Actions, antes de disparar `php artisan dusk`, a variável `DB_DATABASE=testing` é mantida ativa para que o Dusk execute contra a base MySQL separada do runner.

---

## **4. Checklist de Implementação & Testes (Target: 100% Dusk & Preservação Dev DB)**

- [ ] Arquivo `.env.dusk.local` (e `.env.dusk.example`) criado e alinhado com `DB_DATABASE=testing` e `DUSK_DRIVER_URL=http://selenium:4444/wd/hub`.
- [ ] Garantia de criação e persistência do banco `testing` via Sail (`compose.yaml`).
- [ ] Verificação e atualização do `tests/DuskTestCase.php` para assegurar o correto gerenciamento de ambiente Dusk.
- [ ] Alinhamento do arquivo `.env.ci` e `.github/workflows/ci.yml` com a base dedicada `testing`.
- [ ] Atualização da tríade de skills agenticas (`testing-architecture`, `testing-conventions`, `testing-maintenance`) incorporando o padrão de banco de dados Dusk isolado.
- [ ] Validação prática executando a suíte Dusk completa (`vendor/bin/sail dusk`) e confirmando que o banco `plataforma_ead` não sofreu alterações nem truncagem.
