# Hosting your own BOBLOX

This guide puts BOBLOX on a VPS (any Linux server you can SSH into) with **Docker Compose**. Your
**Caddy**, installed on the server itself, gives it HTTPS. Docker only runs BOBLOX; it does not download
or run its own Caddy.

```
visitor ──HTTPS──▶ Caddy (on the server, ports 80/443) ──▶ 127.0.0.1:8080 ──▶ BOBLOX container (Apache + PHP)
                                                                              └─ ./data  (database, uploads)
```

Takes about 10 minutes. You need:

- a VPS with a public IP (1 CPU and 1 GB RAM is plenty),
- a domain (or subdomain) you can point at it, e.g. `boblox.example.com`,
- Caddy installed on the server (`caddy version` works),
- Docker with the Compose plugin (`docker compose version` works).

## 1. Install Docker

If Docker is not installed yet, follow the official steps for your system: https://docs.docker.com/engine/install/
(on Ubuntu: "Install using the apt repository"). Then check:

```sh
docker compose version
```

If you don't want to type `sudo` before every `docker` command, add yourself to the `docker` group
(`sudo usermod -aG docker $USER`, then log out and back in).

## 2. Point your domain at the server

At your domain/DNS provider, add an **A record** for `boblox.example.com` with your server's IPv4
address (and an **AAAA record** for its IPv6 address, if it has one). Make sure ports **80 and 443** are
open. With `ufw`:

```sh
sudo ufw allow 80,443/tcp
```

## 3. Get BOBLOX and configure it

```sh
git clone https://github.com/B0BL0X/BOBLOX.git
cd BOBLOX
cp .env.example .env
nano .env
```

In `.env`, set at least your domain:

| Setting | What it does |
| --- | --- |
| `BOBLOX_SITE_DOMAIN` | Your domain, without `https://`. Used in profile links. |
| `BOBLOX_SITE_NAME` | The site's name (default `BOBLOX`). |
| `BOBLOX_OWNER_DISCORD_URL`, `BOBLOX_CONTACT_EMAIL` | Owner links on the home page and footer. Leave empty to hide them. |
| `BOBLOX_REGISTRATION_OPEN` | Whether sign ups start open. Change it later in **Admin → Site**. |
| `BOBLOX_PORT` | The port on `127.0.0.1` that Caddy forwards to (default `8080`). Change it if something else uses 8080. |

## 4. Start BOBLOX

```sh
docker compose up -d --build
```

The first build takes a minute or two. Check that it is running:

```sh
docker compose ps            # STATUS should say "healthy" after a few seconds
curl -I http://127.0.0.1:8080/
```

The container only listens on `127.0.0.1`, so nobody can reach it directly from outside. Only Caddy can.
It restarts by itself after a crash or a server reboot.

## 5. Tell your Caddy about it

`deploy/Caddyfile` has a ready-made site block. Either copy it into `/etc/caddy/Caddyfile`, or import it
from there:

```caddyfile
# /etc/caddy/Caddyfile
import /home/you/BOBLOX/deploy/Caddyfile
```

Change `boblox.example.com` in it to your domain (and `8080` if you changed `BOBLOX_PORT`), then:

```sh
caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

Caddy gets the HTTPS certificate by itself. The whole block is just:

```caddyfile
boblox.example.com {
	encode zstd gzip
	request_body {
		max_size 10MB
	}
	reverse_proxy 127.0.0.1:8080
}
```

Caddy tells BOBLOX that visitors came in over HTTPS, so login cookies are marked secure and profile links
start with `https://`. BOBLOX also logs each visitor's real IP address instead of Caddy's.

## 6. Make your admin account

Open `https://boblox.example.com` and **sign up straight away**: **the first account becomes the
administrator**. Then you'll see **Admin** in the menu, where you can:

- edit the home page post and welcome note,
- add hats and badges,
- manage users,
- turn on maintenance mode while you work on the site.

If you want to set things up before anyone else can join, start with `BOBLOX_REGISTRATION_OPEN=true`, sign
up, then close sign ups in **Admin → Site** until you're ready.

---

## Everyday jobs

### Updating to a new version

```sh
cd BOBLOX
./deploy/backup.sh             # just in case
git pull
docker compose up -d --build
```

Your data stays in `./data`. Database changes are applied by themselves the first time the new version
runs. If you expect the update to take a while, turn on **Admin → Site → Maintenance mode** first.

### Backups

```sh
./deploy/backup.sh
```

This saves `backups/boblox-YYYYMMDD-HHMMSS.tar.gz` with the database and everything people uploaded. It is
safe to run while the site is up. Copy the backups somewhere off the server now and then. To make one
every night at 4:00, add this with `crontab -e`:

```
0 4 * * * cd /home/you/BOBLOX && ./deploy/backup.sh >/dev/null
```

(The crontab must belong to a user who can run `docker`: root, or a member of the `docker` group.)

### Restoring a backup

```sh
cd BOBLOX
docker compose down
sudo mv data data.old                      # keep the current data until you're happy
mkdir data
sudo tar xzf backups/boblox-20261108-040000.tar.gz -C data
sudo mv data/boblox-backup.db data/boblox.db
docker compose up -d
```

Avatar pictures and thumbnails are not in backups; they are drawn again when needed.

### Logs, restarting, stopping

```sh
docker compose logs -f         # web server and PHP logs (Ctrl+C to stop watching)
docker compose restart         # restart
docker compose down            # stop (data is kept)
```

### Where everything is

| Path | What |
| --- | --- |
| `.env` | your settings |
| `data/boblox.db` | the SQLite database (users, items, badges, settings) |
| `data/uploads/` | T-Shirts, Shirts, Pants, Faces and badge pictures people uploaded |
| `data/thumbs/` | avatar and item pictures (can be deleted; they are drawn again) |
| `data/sessions/` | logins, so nobody is logged out when you update |
| `backups/` | made by `deploy/backup.sh` |

`data/` belongs to the web server user inside the container (uid 33), so use `sudo` to look inside it on
the host.

---

## Troubleshooting

**Caddy says `502 Bad Gateway`.** BOBLOX isn't running or uses another port. Check `docker compose ps`.
The port in the Caddyfile must match `BOBLOX_PORT`.

**No HTTPS certificate.** DNS must point at this server and ports 80/443 must be open. Look at
`journalctl -u caddy --no-pager -n 50`.

**Uploads fail with "too big".** Uploads are limited to 2 MB (images are shrunk anyway). Caddy allows
10 MB and PHP allows 4 MB, so the 2 MB limit always applies first.

**Profile links show the wrong address.** Set `BOBLOX_SITE_DOMAIN` in `.env`, then
`docker compose up -d`.

**I locked myself out / forgot the admin password.** Set a new one from the server:

```sh
docker compose exec boblox php -r '
  $p = new PDO("sqlite:/var/www/html/App_Data/boblox.db");
  $p->prepare("UPDATE users SET password_hash = ?, banned = 0 WHERE username = ?")
    ->execute([password_hash("NEW-PASSWORD-HERE", PASSWORD_DEFAULT), "YOUR-USERNAME"]);'
```

**I turned on maintenance mode and logged out.** The login page still works during maintenance at
`/Login/Default.aspx`. Log in as an admin and turn it off in **Admin → Site**.

---

## Without Docker

BOBLOX is plain PHP, so it also runs on any Apache + PHP host: shared hosting with a `public_html` folder,
or Apache on your own server. See [Running it](../README.md#running-it) in the README. You need PHP 7.4 or
newer with `pdo_sqlite` and `gd`, Apache's `mod_rewrite`, and a writable `App_Data/` folder.
