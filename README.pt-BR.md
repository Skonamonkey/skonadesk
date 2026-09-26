<p align="center">
  <img src="dashboard/assets/icon.svg" alt="SkonaDesk" width="120" />
</p>

<h1 align="center">SkonaDesk</h1>

<p align="center">
  <strong>Gerenciamento de área de trabalho remota auto-hospedado - recursos profissionais, sem assinatura.</strong><br>
  Baseado no servidor de <a href="https://rustdesk.com">RustDesk</a> de código aberto. Funciona com <strong>clientes RustDesk padrão, sem modificações</strong> no Windows, macOS, Linux, iOS e Android.
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/Licence-AGPL--3.0-blue.svg" alt="Licence: AGPL-3.0"></a>
  <a href="https://github.com/Skonamonkey?tab=packages"><img src="https://img.shields.io/badge/Images-GHCR-black?logo=github" alt="GitHub Container Registry"></a>
  <a href="https://buymeacoffee.com/skonamonkey"><img src="https://img.shields.io/badge/Buy%20Me%20a%20Coffee-support%20this%20project-FFDD00?style=flat&logo=buy-me-a-coffee&logoColor=black" alt="Buy Me a Coffee"></a>
</p>

<p align="center">
  <a href="README.md">[Inglês]</a>
</p>

---

## O que é o SkonaDesk?

O RustDesk é uma ferramenta open source de área de trabalho remota. O servidor OSS lida bem com rendezvous e relay, mas não inclui uma camada de API - então recursos como catálogo de endereços, grupos de dispositivos e listas compartilhadas de pares simplesmente não existem, a menos que você os adicione por conta própria ou pague pelo [RustDesk Server Pro](https://rustdesk.com/docs/en/self-host/rustdesk-server-pro/).

O SkonaDesk preenche esse espaço intermediário. É uma pilha auto-hospedada que adiciona a camada de gerenciamento em cima do servidor OSS - catálogo de endereços, grupos, autenticação de relay e um painel administrativo - sem exigir uma licença Pro. Ele roda na sua própria infraestrutura: uma VPS, uma VM Proxmox, um NUC embaixo da escada, o que você tiver.

**Um único `docker compose up` e você tem:**

- 📋 **Catálogos de endereços** - organize suas máquinas com apelidos, notas e tags, sincronizados diretamente com o cliente RustDesk
- 🔑 **Senhas registradas** - guarde a senha de uma máquina junto à entrada, criptografada em repouso, revelada sob demanda e com toda revelação registrada na auditoria *(somente no painel — deliberadamente não sincronizada com os clientes, veja abaixo)*
- 🗂️ **Grupos de dispositivos** - agrupe máquinas por local, equipe ou finalidade; controle quem pode ver o quê
- 👥 **Gerenciamento de usuários** - crie contas, defina administradores e gerencie acessos
- 📡 **Rastreamento ao vivo de dispositivos** - veja quais máquinas estão online agora, o sistema operacional, a versão do cliente e as especificações de hardware
- 🔒 **Autenticação de relay** - apenas usuários autenticados podem iniciar conexões; o abuso anônimo do relay é bloqueado no nível do Rust
- 📊 **Painel administrativo** - interface web limpa com modo claro/escuro, estatísticas em tempo real, log de auditoria e monitoramento de sessões ativas
- 🔗 **Visualização de sessões ativas** - veja exatamente quem está conectado a quê, em tempo real

Sem assinatura. Sem bloqueio de fornecedor. Seus dados continuam na sua infraestrutura.

---

## Para quem é o SkonaDesk?

**O SkonaDesk é uma boa opção se você é:**

- Um entusiasta de homelab que quer um gerenciamento decente de área de trabalho remota sem uma cobrança recorrente
- Uma equipe pequena ou uma pequena empresa que precisa de catálogos de endereços e agrupamento de dispositivos, mas não precisa de gerenciamento de identidade ou políticas de nível corporativo
- Um profissional de TI ou sysadmin gerenciando algumas máquinas para clientes e quer uma solução leve e auto-hospedada
- Alguém que já executa o servidor OSS do RustDesk e quer adicionar a camada de gerenciamento em cima dele

**Você deve considerar o [RustDesk Server Pro](https://rustdesk.com/pricing.html) se precisa de:**

- Integração com OIDC / LDAP / Active Directory
- Autenticação em dois fatores
- Políticas de controle de acesso mais granulares (quem pode conectar em quê)
- Múltiplos servidores relay distribuídos geograficamente
- Gerador de cliente personalizado (instaladores pré-configurados)
- Notificações e alertas por SMTP/e-mail
- Auto-hospedagem do cliente web
- Suporte comercial

O RustDesk Pro também é auto-hospedado - não é um serviço em nuvem. É um produto realmente excelente para equipes que precisam desses recursos. O SkonaDesk foi feito para o espaço entre o servidor OSS básico e o Pro completo: implementações pequenas que só precisam de catálogos de endereços, grupos e uma visão administrativa sem a complexidade de uma pilha corporativa completa.

---

## Recursos

| Recurso | SkonaDesk | RustDesk OSS | RustDesk Pro |
|---------|:---------:|:------------:|:------------:|
| Área de trabalho remota (relay + rendezvous) | ✅ | ✅ | ✅ |
| Auto-hospedado | ✅ | ✅ | ✅ |
| Catálogos de endereços (sincronizados com o cliente) | ✅ | ❌ | ✅ |
| Senhas registradas (criptografadas, revelação auditada) | ✅ | ❌ | ✅ |
| Grupos de dispositivos | ✅ | ❌ | ✅ |
| Gerenciamento de usuários | ✅ | ❌ | ✅ |
| Painel administrativo / console web | ✅ | ❌ | ✅ |
| Visualização de sessões ativas | ✅ | ❌ | ✅ |
| Log de auditoria / conexões | ✅ | ❌ | ✅ |
| Autenticação de relay | ✅ | ❌ | ✅ |
| Informações de hardware do dispositivo (CPU/RAM/OS) | ✅ | ❌ | ✅ |
| OIDC / LDAP / 2FA | ❌ | ❌ | ✅ |
| Políticas de controle de acesso | ❌ | ❌ | ✅ |
| Múltiplos servidores relay (geo) | ❌ | ❌ | ✅ |
| Gerador de cliente personalizado | ❌ | ❌ | ✅ |
| SMTP / notificações por e-mail | ❌ | ❌ | ✅ |
| Auto-hospedagem do cliente web | ❌ | ❌ | ✅ |
| Custo de licença | Gratuito | Gratuito | Pago (por usuário) |

---

## Capturas de tela

<p align="center">
  <img src="docs/screenshots/pt-BR/dashboard.png" alt="Painel do SkonaDesk" width="80%" />
  <br><em>Painel com estatísticas em tempo real, status dos dispositivos, sessões ativas e atividade recente</em>
</p>

<p align="center">
  <img src="docs/screenshots/pt-BR/devices.png" alt="Página de dispositivos" width="80%" />
  <br><em>Gerenciamento de dispositivos - ícones de sistema operacional, versão do cliente, CPU, RAM e IP WAN em um só lugar</em>
</p>

---

## Instalação rápida

Se você estiver em um servidor Linux novo com Docker instalado, o instalador interativo cuida de tudo:

```bash
curl -fsSL https://raw.githubusercontent.com/Skonamonkey/skonadesk/main/install.sh -o install.sh && bash install.sh
```

O script vai pedir seu domínio (ou IP), gerar segredos seguros, escrever o seu `.env` e iniciar a pilha.

---

## Instalação no Proxmox LXC *(linha única)*

Execute o comando abaixo diretamente no host Proxmox. Ele cria um contêiner LXC Debian 12, instala o Docker e configura a pilha completa do SkonaDesk de forma interativa - solicitando endereço do servidor, porta do painel e credenciais de administrador.

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/Skonamonkey/skonadesk/main/proxmox/ct/skonadesk.sh)"
```

O instalador detecta automaticamente o IP do contêiner como endereço padrão do servidor. Se você pretende colocá-lo atrás de um reverse proxy, informe seu domínio no prompt em vez de aceitar o IP padrão.

Ao final, a URL do painel e os requisitos de porta do firewall são exibidos no terminal.

> **Atualização:** execute novamente a mesma linha única no host Proxmox e escolha a opção de atualização, ou faça isso de dentro do LXC:
> ```bash
> cd /srv/skonadesk && docker compose pull && docker compose up -d --force-recreate
> ```

## Instalação manual

### Etapa 1 - Clone a stack e configure

```bash
git clone https://github.com/Skonamonkey/skonadesk.git /srv/skonadesk
cd /srv/skonadesk
cp .env.example .env
nano .env
```

Este repositório contém a API, o dashboard e a configuração do Docker Compose. O binário `hbbs` com patch **não** é compilado aqui - ele é uma imagem pronta (`ghcr.io/skonamonkey/skonadesk-hbbs:latest`) puxada do repositório [skonadesk-hbbs](https://github.com/Skonamonkey/skonadesk-hbbs). O Docker o baixa automaticamente quando você inicia a stack na Etapa 3. Não é necessário nenhum passo de build separado.

### Etapa 2 - Configure o `.env`

**Com domínio e SSL (recomendado para implementações expostas à internet):**

```env
RELAY_HOST=your.domain.com
DOMAIN=your.domain.com
API_PUBLIC_URL=https://your.domain.com

JWT_SECRET=<run: openssl rand -hex 32>
APP_SECRET=<run: openssl rand -hex 32>

ADMIN_USER=yourname          # não use 'admin'
ADMIN_PASS=a-strong-password

DB_PATH=/data/skonadesk.db
PORT=21114
```

**Com endereço IP ou hostname DDNS (sem SSL):**

```env
RELAY_HOST=192.168.1.50      # ou your.ddns.net
DOMAIN=192.168.1.50
API_PUBLIC_URL=http://192.168.1.50:21114

JWT_SECRET=<run: openssl rand -hex 32>
APP_SECRET=<run: openssl rand -hex 32>

ADMIN_USER=yourname
ADMIN_PASS=a-strong-password

DB_PATH=/data/skonadesk.db
PORT=21114
```

Gere segredos seguros com:
```bash
openssl rand -hex 32
```

### Etapa 3 - Inicie a stack

```bash
docker compose -f docker-compose.prod.yml up -d
```

Verifique se tudo está em execução:

```bash
docker compose -f docker-compose.prod.yml ps
```

Você deve ver quatro contêineres: `skonadesk-hbbs`, `skonadesk-hbbr`, `skonadesk-api`, `skonadesk-dashboard`.

### Etapa 4 - Configuração de SSL (se estiver usando um domínio)

Adicione dois hosts proxy no [Nginx Proxy Manager](https://nginxproxymanager.com) (ou em qualquer reverse proxy):

| Domínio | Host de encaminhamento | Porta de encaminhamento | SSL |
|--------|-------------------------|-------------------------|-----|
| `your.domain.com` | `skonadesk-api` | `21114` | Let's Encrypt |
| `dashboard.your.domain.com` | `skonadesk-dashboard` | `80` | Let's Encrypt |

> **O NPM precisa estar na mesma rede Docker** para resolver `skonadesk-api` e `skonadesk-dashboard` pelo hostname. O SkonaDesk cria uma rede chamada `skonadesk`. Você tem três opções:
>
> **Opção A - Edite o arquivo compose do NPM (recomendado, persistente):**
> ```yaml
> networks:
>   skonadesk:
>     external: true
>
> services:
>   app:
>     # ... sua configuração atual do NPM ...
>     networks:
>       - default
>       - skonadesk
> ```
> Depois reinicie o NPM: `docker compose down && docker compose up -d`
>
> **Opção B - Linha única rápida (não persiste entre reinícios do contêiner NPM):**
> ```bash
> docker network connect skonadesk npm
> ```
> Isso funciona imediatamente e sem reiniciar, mas é perdido se o contêiner NPM for recriado.
>
> **Opção C - Ignore a rede Docker completamente:** use o IP do host e as portas mapeadas (`YOUR-SERVER-IP:21114` e `YOUR-SERVER-IP:8080`) como destinos de encaminhamento no NPM em vez dos hostnames dos contêineres.

Sem SSL? Pule esta etapa - acesse o painel em `http://YOUR-IP:8080`.

### Etapa 5 - Configure o cliente RustDesk

Abra a página **Server** no painel - ela mostra sua chave pública e as configurações do cliente já preenchidas com botões de cópia.

Ou configure manualmente no cliente RustDesk em **Settings → Network:**

| Campo | SSL (domínio) | Sem SSL (IP/DDNS) |
|-------|----------------|-------------------|
| ID/Relay Server | `your.domain.com` | `192.168.1.50` |
| API Server | `https://your.domain.com` | `http://192.168.1.50:21114` |
| Key | *(da página Server do painel)* | *(da página Server do painel)* |

---

## Cenários de implantação

### Cenário A - VPS com domínio + SSL *(recomendado para segurança do transporte da API)*

Um reverse proxy (Nginx Proxy Manager é a opção mais fácil) encerra HTTPS e encaminha para os contêineres da API e do dashboard. Todo o tráfego entre clientes e a API fica criptografado em trânsito.

> **Observação sobre a exposição do dashboard:** em uma VPS pública, o dashboard fica exposto na internet, o que amplia a superfície de ataque em comparação com uma implantação em LAN doméstica. Para maior segurança, restrinja o acesso ao dashboard via VPN ou allowlist de firewall, para que só você consiga acessá-lo - a API precisa continuar pública para os clientes, mas o dashboard não precisa.

**Firewall / painel da hospedagem - abra estas portas:**

| Porta | Protocolo | Finalidade |
|------|----------|---------|
| 21115 | TCP | Teste de tipo NAT |
| 21116 | TCP + UDP | Rendezvous (hbbs) |
| 21117 | TCP | Relay (hbbr) |
| 21118 | TCP | WebSocket rendezvous |
| 21119 | TCP | WebSocket relay |
| 443 | TCP | API + Dashboard (reverse proxy) |
| 80 | TCP | Redirecionamento HTTP → HTTPS |

Não é necessário encaminhamento de portas em uma VPS - o firewall do painel da hospedagem já cuida disso.

### Cenário B - Servidor doméstico / VM Proxmox exposto à internet

Encaminhe estas portas do seu roteador para o IP LAN da máquina:

| Externa | Interna | Protocolo | Finalidade |
|----------|----------|----------|---------|
| 21115 | 21115 | TCP | Teste de tipo NAT |
| 21116 | 21116 | TCP + UDP | Rendezvous |
| 21117 | 21117 | TCP | Relay |
| 21118 | 21118 | TCP | WebSocket rendezvous |
| 21119 | 21119 | TCP | WebSocket relay |
| 443 | 443 | TCP | API + Dashboard via reverse proxy *(SSL - recomendado)* |
| 21114 | 21114 | TCP | API direta *(somente sem SSL - veja a observação abaixo)* |
| 8080 | 8080 | TCP | Dashboard direto *(somente sem SSL - não recomendado externamente)* |

> **Importante:** a porta 21114 (API) **precisa estar acessível para todo cliente que se conectar** - ela é necessária para a autenticação JWT do relay. Sem ela, os clientes conseguem registrar-se no servidor rendezvous, mas o relay rejeita as conexões (elas ficam travadas em "Connecting..."). Encaminhar 21115-21119 sem 21114 não resolve nada para clientes externos.
>
> **Dica de segurança:** a abordagem fortemente recomendada é colocar a API atrás de um reverse proxy com SSL (porta 443) - assim você não encaminha a porta 21114 diretamente. A porta 8080 (dashboard) deve ser sempre mantida apenas na LAN ou via VPN; clientes RustDesk normais nunca precisam acessar o dashboard.

### Cenário C - Apenas LAN / apenas VPN

A opção mais simples. Sem encaminhamento de portas, sem domínio, sem SSL. Todos os clientes se conectam ao IP LAN da máquina. Perfeito para um escritório onde todas as máquinas estão na mesma rede, ou para qualquer configuração em que o acesso remoto passe primeiro por uma VPN.

---

## Segurança e endurecimento

O SkonaDesk é fornecido no estado em que se encontra. Você é responsável por proteger sua própria implantação.

### Checklist mínimo

- [ ] Defina um `ADMIN_USER` pouco óbvio (não `admin` - é a primeira coisa que atacantes tentam)
- [ ] Use um `ADMIN_PASS` forte e troque-o após a configuração
- [ ] Gere valores aleatórios para `JWT_SECRET` e `APP_SECRET` com `openssl rand -hex 32`
- [ ] Para implementações expostas à internet, use SSL - não exponha a API nem o dashboard via HTTP puro em IP público
- [ ] Restrinja o SSH no servidor: autenticação por chave apenas, desative login por senha
- [ ] Faça backup de `./data/id_ed25519` (a chave privada do servidor) - se ela for perdida, todos os clientes precisam ser reconfigurados
- [ ] Se você registrou senhas de catálogo de endereços, faça backup de `./data/` antes de rotacionar o `JWT_SECRET` — as senhas registradas são criptografadas com uma chave derivada dele e não podem ser recuperadas depois
- [ ] Mantenha as imagens Docker atualizadas periodicamente

### Proteção contra força bruta

A API aplica limitação de taxa de login por combinação de **IP + usuário**:

- **5 tentativas falhas** em 15 minutos acionam um bloqueio de 15 minutos para aquele par IP/usuário
- Um login bem-sucedido limpa o contador imediatamente
- Eventos de bloqueio são registrados: `docker logs skonadesk-api | grep brute-force`
- Clientes bloqueados recebem HTTP 429: *"Too many failed attempts. Try again in N minute(s)."*

Usar IP+usuário (em vez de apenas IP) significa que um dispositivo mal configurado na sua LAN tentando as credenciais erradas vai bloquear apenas *aquele usuário* a partir *daquele IP* - não todos os usuários da rede inteira.

> **Observação:** o estado de bloqueio fica em memória e é reiniciado se o contêiner da API for reiniciado. Para bloqueios persistentes após reinícios, coloque um reverse proxy com limitação de taxa própria (por exemplo, os limites embutidos do Nginx Proxy Manager) na frente.

### Senhas registradas — e o que elas não são

Uma entrada do catálogo de endereços pode carregar uma senha registrada manualmente: defina-a no painel, armazenada criptografada, revelada sob demanda pelo proprietário da entrada ou por um administrador. Toda revelação é gravada no log de auditoria como `ab_password_view`.

**Esta não é a senha de conexão salva do RustDesk, e não é sincronizada com os clientes.**

A senha salva do próprio RustDesk fica em cada *cliente*, em `~/.config/rustdesk/peers/<id>.toml`, criptografada com a chave daquela máquina. Ela nunca é enviada ao servidor, então o SkonaDesk não tem nada para guardar nem para devolver. O que este recurso armazena é uma nota separada, digitada por uma pessoa, com a senha de uma máquina, mantida junto à máquina no seu painel.

As consequências práticas:

- **Registrar uma senha não permite que um cliente se conecte sem digitá-la.** A sincronização de `peers` que o cliente faz carrega apenas apelido, nota e tags. Senhas nunca são incluídas nesse payload.
- **Copiar um catálogo de endereços entre usuários não move as senhas dos clientes RustDesk** — não existe cópia delas no servidor para mover, de nenhum dos lados.
- **`has_password` é um sinal exclusivo do painel.** Ele informa ao navegador se deve renderizar o controle de revelação; não faz parte da sincronização do cliente.
- **É uma nota, não um cofre.** Trate como uma conveniência para manter registro da senha de uma máquina em algum lugar sob seu controle, não como SSO ou gerenciador de segredos.

Como é armazenado:

| Propriedade | Valor |
|---|---|
| Cifra | AES-256-GCM, IV aleatório de 12 bytes por registro, tag de autenticação armazenada junto |
| Chave | HKDF-SHA256 derivada do `JWT_SECRET`, sob um rótulo fixo de separação de domínio |
| Formato | `v1:<iv>:<tag>:<ciphertext>` — versionado para que uma futura mudança de chave ou cifra seja detectável |
| Falha segura | Sem chave utilizável (ausente, vazia ou o padrão `changeme`) o armazenamento e a revelação são recusados com `503` e nada é gravado |

> **Se você rotacionar o `JWT_SECRET`, as senhas registradas ficam ilegíveis.** A chave do cofre é derivada dele, então alterar o segredo quebra permanentemente a descriptografia de todo valor armazenado — as revelações retornarão `422`. Não há caminho de recriptografia. Rotacione o `JWT_SECRET` e você precisará digitar as senhas registradas novamente. Faça backup de `./data/` antes de rotacionar.

### O que o SkonaDesk não oferece

- **Controle de acesso por par** - qualquer usuário autenticado pode tentar conectar em qualquer dispositivo cujo ID ele conheça. As senhas por dispositivo definidas no cliente RustDesk são a única restrição por dispositivo.
- **Autenticação multifator**
- **Auditoria do conteúdo da sessão** - as conexões são criptografadas ponta a ponta entre os pares; o servidor não vê o que foi transmitido

### Comportamento da autenticação do relay

O `hbbs` com patch valida um token JWT em cada `PunchHoleRequest`. Clientes sem um token de login válido recebem uma resposta `LICENSE_MISMATCH` antes que qualquer tráfego de relay seja iniciado.

### Matriz de configuração do cliente

Nem toda máquina precisa da mesma configuração. Esta tabela mostra exatamente o que funciona e por quê - verificado no código-fonte do `hbbs` com patch.

| Chamador: Server | Chamador: JWT | Chamador: Key | Chamado: Server | Chamado: JWT | Chamado: Key | Resultado |
|:---:|:---:|:---:|:---:|:---:|:---:|---|
| ✅ | ✅ | ✅ | ✅ | ➖ | ✅ | ✅ Funciona - ambos os canais rendezvous são criptografados |
| ✅ | ✅ | ✅ | ✅ | ➖ | ❌ | ✅ Funciona - chamador criptografado, callee com rendezvous em texto puro |
| ✅ | ✅ | ❌ | ✅ | ➖ | ✅ | ✅ Funciona - callee criptografado, chamador com rendezvous em texto puro |
| ✅ | ✅ | ❌ | ✅ | ➖ | ❌ | ✅ Funciona - ambos os canais rendezvous em texto puro |
| ✅ | ❌ | ✅ | ✅ | ➖ | ✅ | ❌ **Bloqueado** - o chamador não tem JWT, é rejeitado no rendezvous |
| ✅ | ❌ | ❌ | ✅ | ➖ | ✅ | ❌ **Bloqueado** - o chamador não tem JWT, é rejeitado no rendezvous |
| ✅ | ✅ | ✅ | ❌ | ➖ | ➖ | ❌ **Bloqueado** - o callee não está registrado, aparece offline |
| ❌ | ➖ | ➖ | ✅ | ➖ | ✅ | ❌ **Bloqueado** - o chamador não consegue alcançar o servidor rendezvous |

**Legenda:**
- **➖** = não aplicável / não afeta o resultado
- **Callee JWT** é sempre ➖ - o callee (a máquina que está sendo acessada) nunca precisa estar logado
- **Key** controla apenas a criptografia de transporte no canal rendezvous - ela não bloqueia conexões
- **O conteúdo da sessão peer-to-peer é sempre criptografado de ponta a ponta** independentemente da configuração da key - isso é um mecanismo separado, entre os dois clientes diretamente

**Por que a key e o HTTPS são fortemente recomendados, mesmo que as conexões funcionem sem eles:**

O token JWT trafega dentro de cada `PunchHoleRequest` no canal rendezvous. Sem a key configurada no chamador, esse canal fica em texto puro - o JWT fica visível na rede. Um JWT interceptado dá a um atacante 7 dias de acesso ao relay (tempo de vida do token). Sem HTTPS na API, sua senha e seu JWT também ficam visíveis no momento do login - uma senha interceptada dá acesso permanente até ser alterada.

| Canal | Sem proteção - o atacante vê | Consequência |
|---|---|---|
| **API sem HTTPS** | Senha + token JWT | Acesso permanente à conta + 7 dias de acesso ao relay |
| **Rendezvous do chamador sem key** | JWT em `PunchHoleRequest` | 7 dias de acesso ao relay |
| **Rendezvous do callee sem key** | ID do par, hostname, SO, tempo | Apenas reconhecimento - sem risco de credenciais |

Nada disso é obrigatório - as conexões funcionam sem isso. Mas o esforço marginal para configurar a key nos dois lados e habilitar HTTPS é pequeno, e a proteção é real.

---

## Solução de problemas

### "Too many failed attempts" / não consigo fazer login

A proteção contra força bruta da API bloqueou sua combinação IP+usuário após 5 tentativas falhas de login. Aguarde 15 minutos para o bloqueio expirar, ou reinicie o contêiner da API para limpá-lo imediatamente:

```bash
docker compose restart api
```

Para ver quais IPs foram bloqueados:
```bash
docker logs skonadesk-api | grep brute-force
```

### Erro "Key mismatch"

- O chamador não está logado na API. Faça login pelo cliente RustDesk (ícone de chave/pessoa no canto superior direito) e tente novamente.
- Confira se o campo Key em RustDesk Settings → Network corresponde à chave exibida na página Server do painel.

### Erro "Failed to secure tcp / deadline elapsed"

- O contêiner `hbbs` está executando a imagem padrão `rustdesk/rustdesk-server` em vez do `skonadesk-hbbs` com patch. Verifique com:
  ```bash
  docker inspect skonadesk-hbbs --format '{{.Config.Image}}'
  ```
  Ele deve mostrar `skonadesk-hbbs:latest` (ou o caminho do GHCR), e não `rustdesk/rustdesk-server:latest`.

### Não consigo conectar ao dashboard

- Verifique se `skonadesk-dashboard` está em execução: `docker compose ps`
- Verifique se a API está acessível: `curl http://localhost:21114/api/login-options`
- Em configurações com SSL, confirme se o host proxy do NPM aponta para `skonadesk-api:21114` (e não para a porta do dashboard)

### Dispositivos não aparecem / ficam offline

- O dispositivo precisa ter o endereço correto do API Server configurado em RustDesk Settings → Network
- O dispositivo envia heartbeats a cada 3 segundos. Se não for visto nos últimos 2 minutos, ele aparece como offline.
- Verifique os logs da API: `docker logs skonadesk-api --tail 50`

### Alterando a senha de administrador após a primeira execução

```bash
docker exec -it skonadesk-api node -e "
const db = require('./db').getDb();
const bcrypt = require('bcryptjs');
db.prepare(\"UPDATE users SET password=? WHERE username=?\")
  .run(bcrypt.hashSync('new-password', 10), 'youradminname');
console.log('done');
"
```

---

## Detalhes técnicos

### Por que um hbbs com patch é necessário

O binário padrão do `hbbs` OSS do RustDesk tem dois comportamentos que quebram integrações de API de terceiros:

**Bug 1 - "Failed to secure tcp: deadline elapsed"**

Quando um cliente RustDesk tem uma sessão ativa da API, ele chama `secure_tcp()` antes de enviar um `PunchHoleRequest`. Isso aguarda até 18 segundos para o servidor enviar uma mensagem `KeyExchange`. O `hbbs` padrão nunca inicia isso - ele espera pelo cliente. Os dois lados esperam para sempre → timeout → a conexão falha.

*Correção:* o hbbs com patch implementa o handshake `KeyExchange` em duas fases corretamente. Em cada nova conexão TCP, o servidor assina sua chave pública efêmera com a chave de assinatura do servidor e a envia ao cliente (fase 1). O cliente responde com sua própria chave pública efêmera selada contra a chave do servidor (fase 2). Ambos derivam uma chave simétrica compartilhada (XSalsa20-Poly1305 via libsodium) e todo o tráfego rendezvous subsequente nessa conexão é criptografado. Isso é equivalente à abordagem usada no fork de lejianwen e é a solução correta para o deadlock original.

**Bug 2 - "Key mismatch" em clientes Windows**

Os binários padrão do RustDesk para Windows leem a licença embutida no binário antes de verificar o arquivo de configuração do usuário. Essa chave embutida (`OeVuKk5nlHiXp+APNn0Y3pC1Iwpwn44JGqrQCsWqmBw=`) nunca vai coincidir com a chave de qualquer servidor personalizado.

*Correção:* a verificação de licença é pulada por completo. A autenticação é fornecida pela autenticação JWT do relay.

**Criptografia de transporte:** o canal TCP de rendezvous entre cliente e `hbbs` é criptografado em transporte pelo handshake KeyExchange descrito acima. A criptografia ponta a ponta entre pares não é afetada (ela acontece no nível dos pares, de forma independente). Se você também usar SSL via reverse proxy, o trecho cliente→proxy também fica criptografado na camada TLS.

### Arquitetura

```
Cliente RustDesk
      │
      ├── TCP :21116 / UDP :21116 ──► skonadesk-hbbs  (rendezvous com patch)
      │                                   │ validação JWT em cada PunchHoleRequest
      ├── TCP :21117 ───────────────► skonadesk-hbbr  (relay padrão)
      │
      ├── HTTPS :443 ──► Nginx Proxy Manager ──► skonadesk-api       :21114
      │                                      └──► skonadesk-dashboard :8080
      │
      └── WebSocket :21118 ──► skonadesk-hbbs
```

### Estrutura dos arquivos da stack

```
skonadesk/
├── .env.example
├── docker-compose.yml          # dev (builds locais)
├── docker-compose.prod.yml     # prod (imagens GHCR)
├── install.sh                  # instalador interativo de uso único
├── api/                        # servidor API Node.js (porta 21114)
│   ├── Dockerfile
│   ├── server.js
│   ├── db.js
│   ├── auth.js
│   └── routes/
│       ├── login.js            # auth, heartbeat
│       ├── ab.js               # catálogo de endereços (14 endpoints)
│       ├── heartbeat.js
│       └── admin.js            # users, peers, groups, stats, sessions
└── dashboard/                  # interface administrativa PHP 8.2
    ├── Dockerfile
    ├── home.php                # painel com estatísticas em tempo real
    ├── devices.php             # lista de dispositivos com informações de SO/hardware
    ├── sessions.php            # monitor de sessões ativas
    ├── addressbook.php
    ├── users.php
    ├── groups.php
    ├── audit.php
    ├── server.php              # chave pública + guia de configuração do cliente
    ├── profile.php             # alteração de senha
    └── includes/
        ├── config.php
        ├── auth.php
        ├── api.php
        └── layout.php
```

### Compilando o hbbs com patch a partir do código-fonte

Imagens prontas são fornecidas via GHCR. Se você quiser compilar a partir do código-fonte (por exemplo, para auditar ou modificar os patches):

```bash
git clone https://github.com/Skonamonkey/skonadesk-hbbs.git
cd skonadesk-hbbs
git submodule update --init --recursive
docker build --no-cache -f Dockerfile.skonadesk -t skonadesk-hbbs:latest .
```

Tempo de build: ~2 minutos com cache de camada do Docker; ~10 minutos do zero em uma máquina moderna.

Para transferir para um servidor remoto:

```bash
docker save skonadesk-hbbs:latest | gzip > /tmp/skonadesk-hbbs.tar.gz
scp /tmp/skonadesk-hbbs.tar.gz user@your-server:/tmp/
# No servidor:
docker load < /tmp/skonadesk-hbbs.tar.gz
```

---

## Status do projeto e manutenção

O SkonaDesk é um projeto de **manutenção comunitária, feito no tempo livre** por um desenvolvedor solo. Ele é estável, é usado ativamente em produção e funciona bem como está - mas não é um produto em tempo integral com roadmap e cronograma de lançamentos.

**O que você pode esperar:**
- Correções de segurança aplicadas assim que for praticamente possível
- Correções de bugs quando reportados e reproduzíveis
- Pedidos de recurso considerados e implementados quando houver tempo
- O relay `hbbr` é intencionalmente fixado em uma versão específica do RustDesk (atualmente `1.1.15`) em vez de `latest`, para que mudanças automáticas do upstream não quebrem sua instalação silenciosamente

**Sobre o que você deve ajustar suas expectativas:**
- Os tempos de resposta em issues/PRs podem variar - a vida real vem primeiro
- Não há oferta de suporte comercial
- Se está funcionando para você, não há pressão para atualizar - *"if it ain't broke, don't fix it"* é uma estratégia perfeitamente válida aqui
- **Imagens ARM64 (`linux/arm64`) são publicadas**, mas são de melhor esforço da comunidade - não tenho hardware ARM para testar, então problemas específicos de ARM podem levar mais tempo para diagnosticar ou podem precisar de ajuda da comunidade para reproduzir

Se você achar útil, a melhor forma de ajudar o projeto é abrir issues para bugs, enviar PRs com correções e divulgar em comunidades de homelab. Contribuições são sempre bem-vindas.

---

## Créditos e licença

O SkonaDesk é construído sobre o [servidor open source do RustDesk](https://github.com/rustdesk/rustdesk-server). O protocolo, as definições protobuf e a lógica central de rendezvous/relay são obra do RustDesk. Este projeto adiciona a camada de API e dashboard e corrige os dois bugs descritos acima, que impedem qualquer API de terceiros de funcionar com o cliente padrão.

RustDesk é © RustDesk Ltd e colaboradores, licenciado sob AGPL-3.0.
SkonaDesk é © Skonamonkey e colaboradores, licenciado sob [AGPL-3.0](LICENSE).

Se o SkonaDesk economizar seu dinheiro, considere [apoiar o projeto RustDesk](https://github.com/sponsors/rustdesk) - eles construíram a base sobre a qual isso roda.

Se o SkonaDesk tiver sido útil para você, você também pode [me pagar um café](https://buymeacoffee.com/skonamonkey) - é algo apreciado, mas nunca esperado.
