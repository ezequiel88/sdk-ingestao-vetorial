# Ingestao Vetorial SDKs

Repositorio publico dedicado aos SDKs do Ingestao Vetorial.

## Estrutura

- `sdk/python/` — pacote para PyPI
- `sdk/js/` — pacote para npm
- `sdk/php/` — pacote para Packagist
- `sdk/csharp/` — pacote para NuGet
- `sdk/go/` — modulo Go publico
- `sdk/flutter/` — pacote Dart/Flutter para pub.dev
- `.github/` — CI e automacao de release do repositorio standalone
- `.changeset/` — metadados de versionamento por SDK

## Fluxo de release

1. PRs que alterem o comportamento publico de qualquer SDK devem incluir um arquivo em `.changeset/`.
2. Ao entrar na `main`, o workflow `sdk-release-main.yml` valida os SDKs, calcula as proximas versoes, atualiza os manifests necessarios, remove os changesets consumidos e cria tags.
3. As tags disparam os workflows por linguagem para publicar em PyPI, npm, Packagist, NuGet e GitHub Releases.

## Ajustes apos a criacao do repositorio

Confirme estes pontos sempre que a URL publica ou a organizacao mudar:

- `sdk/python/pyproject.toml` em `project.urls.Homepage`
- `sdk/go/go.mod` no caminho do modulo
- `sdk/go/README.md` no exemplo de `go get`
- `sdk/csharp/IngestaoVetorial.SDK/IngestaoVetorial.SDK.csproj` em `RepositoryUrl` e `PackageProjectUrl`
- READMEs que mencionem o caminho anterior do monorepo

## Secrets esperadas no GitHub

- Environment `pypi`: `PYPI_API_TOKEN`
- Environment `npm`: `NPM_TOKEN`
- Environment `packagist`: `PACKAGIST_USERNAME`, `PACKAGIST_API_TOKEN`
- Environment `nuget`: `NUGET_API_KEY`
- Environment `pubdev`: `PUB_DEV_ACCESS_TOKEN`

O SDK Go usa apenas o `GITHUB_TOKEN` automatico para criar GitHub Releases.

Se o repositório no GitHub ainda estiver vazio, o primeiro push da branch `main` precisa acontecer antes da configuração de Actions/environments.

Bootstrap dos environments via PowerShell: `./scripts/setup-github-release.ps1`

Checklist operacional de setup e validacao: `docs/RELEASE_SETUP.md`
