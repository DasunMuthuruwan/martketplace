# Developer Tooling Notes (Local Machine)

| Field | Value |
|---|---|
| Date | 2026-10-11 |
| Scope | The owner's Windows development machine. These are **global** tools shared by all projects. |
| Rule | No global change (Composer update, Composer config, PATH) without the owner's explicit approval. Nothing below has been executed. |

## 1. Global Composer Update — Plan (Awaiting Approval)

### 1.1 Current State [VERIFIED 2026-10-11]

| Item | Value |
|---|---|
| Installed version | **Composer 2.8.2** (2024-10-29) |
| Location | `C:\ProgramData\ComposerSetup\bin\composer.phar`, run through `composer.bat` / `composer` shims, which call **whichever `php` is first on PATH** (currently `C:\php8.2\php\php.exe`, PHP 8.2.12) |
| Write access | `C:\ProgramData\ComposerSetup\bin` is **not writable without Administrator rights**. A self-update must run from an elevated terminal. |
| Known advisories affecting 2.8.2 (from `composer diagnose`) | 11 advisories: **6 high**, 4 medium, 1 low. Examples: CVE-2026-84361 / CVE-2026-40261 / CVE-2026-40176 (command execution via malicious Perforce sources), CVE-2026-59948 (file write outside `vendor` via a malicious transitive package), CVE-2026-45793 (GITHUB_TOKEN disclosure in Actions logs), CVE-2024-51736 (bundled symfony/process command hijack on Windows). |

### 1.2 Target Version [VERIFIED from getcomposer.org/versions and Packagist]

| Item | Value |
|---|---|
| Target | **Composer 2.10.3** (latest stable, released 2026-08-27, maintained branch) |
| PHP requirement | `^7.2.5 \|\| ^8.0` (min PHP 7.2.5), so it is compatible with **all** installed PHP versions: 7.4, 8.1, 8.2.12 and 8.4.26 |
| Alternative | 2.2.30 LTS (critical security fixes only, until 2026-12-31). Not recommended: older feature set, near end of life. |
| Compatibility | Composer 2.x minor updates are backward compatible for `composer.json` / `composer.lock`. **Updating Composer does not change any project's dependencies or lock files.** `composer.lock` only changes when you run `update`/`require` in a project. |

### 1.3 Recommended Update Procedure (Run by the Owner, After Approval)

Open **PowerShell as Administrator**:

```powershell
# 1. Record the current version
composer --version

# 2. Manual backup of the current phar (in addition to Composer's own rollback copy)
Copy-Item "C:\ProgramData\ComposerSetup\bin\composer.phar" "C:\ProgramData\ComposerSetup\bin\composer-2.8.2.phar.bak"

# 3. Update to the exact target version (pinned, not "latest")
composer self-update 2.10.3

# 4. Verify
composer --version
composer diagnose
```

Then check one existing project without changing it: run `composer validate` and `composer install --dry-run` in a project folder (dry run, no file changes).

### 1.4 Rollback

| Method | Command (elevated PowerShell) |
|---|---|
| Composer's built-in rollback (uses the backup Composer keeps from the last self-update) | `composer self-update --rollback` |
| Explicit version | `composer self-update 2.8.2` |
| Manual restore (if the above fails) | `Copy-Item "C:\ProgramData\ComposerSetup\bin\composer-2.8.2.phar.bak" "C:\ProgramData\ComposerSetup\bin\composer.phar" -Force` |

Rollback should rarely be needed. The usual reason would be a plugin in an old project that is incompatible with Composer 2.10. That plugin would then need updating in that project.

### 1.5 Alternative Without a Global Change

If you prefer not to touch the global Composer yet, the marketplace can use its **own** Composer phar, e.g. `C:\php8.4\composer.phar` (downloaded and checksum-verified from getcomposer.org) and run as `C:\php8.4\php.exe C:\php8.4\composer.phar …`. This affects no other project. **It also needs approval** (it adds a tool to the machine). Decide before Phase 5 scaffolding.

**Status:** Awaiting owner approval. The marketplace work is not blocked, since no dependencies are installed before Phase 5.

## 2. Invalid GitHub OAuth Token in Global Composer Config

### 2.1 Issue

`composer diagnose` reports: *"The oauth token for github.com seems invalid"*. Composer uses this token only to raise GitHub API rate limits and to access private GitHub repositories. Public Packagist packages normally install without it. An invalid token can cause authentication errors when Composer falls back to GitHub downloads.

The token value was **not read, printed or changed**. The commands below show only **whether** a token is configured, never its value.

### 2.2 Inspect (Without Revealing the Token)

PowerShell:

```powershell
# Where the global Composer config lives (prints a folder path only)
composer config --global home

# Which auth hosts are configured in the global auth.json (prints host names only, never values)
$auth = Join-Path (composer config --global home) "auth.json"
if (Test-Path $auth) {
    $j = Get-Content $auth -Raw | ConvertFrom-Json
    if ($j.'github-oauth') { $j.'github-oauth'.PSObject.Properties.Name } else { "no github-oauth entries" }
} else { "no global auth.json" }

# Also check whether a token is supplied through an environment variable (prints True/False only)
[bool]$env:COMPOSER_AUTH
```

**Avoid** `composer config --global github-oauth.github.com` and `composer config --global --list`, and don't `cat` / `Get-Content` the `auth.json` file. These can print the token in clear text to the screen and terminal logs.

### 2.3 Remove the Invalid Token

```powershell
composer config --global --unset github-oauth.github.com
composer diagnose   # the github.com oauth warning should be gone
```

Optionally revoke the old token on GitHub as well (Settings → Developer settings → Personal access tokens), since it may still exist there with a different value or have expired.

### 2.4 Configure a Replacement (Only If Needed)

Most work needs **no token**. Add one only if you hit GitHub rate limits or use private GitHub repositories.

1. GitHub → Settings → Developer settings → **Fine-grained personal access token**.
   - Public packages only: no repository access and no permissions are needed (read-only public access), with a short expiry (e.g. 90 days).
   - Private repositories: grant **Contents: Read-only** on only those repositories.
2. Store it **without putting it in your shell history**. Let Composer prompt for it. When Composer hits a rate limit, it asks for a token interactively and offers to save it, so nothing goes into history. Alternatively, run:
   ```powershell
   $t = Read-Host -AsSecureString "GitHub token"
   $plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($t))
   composer config --global --auth github-oauth.github.com $plain
   Remove-Variable plain, t
   ```
3. Verify with `composer diagnose`. It should report the token as valid without printing it.

**Never** commit `auth.json`, paste tokens into project files or `.env`, or share `composer diagnose -vvv` output publicly without checking it first.

**Status:** Documented for the owner to run. No change was made.
