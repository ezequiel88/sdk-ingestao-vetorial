# Setup de Release dos SDKs

Este repositorio usa um fluxo em dois estagios:

1. `sdk-release-main.yml` roda na `main`, valida os SDKs, aplica os bumps de versao e cria as tags.
2. Cada tag dispara o workflow de publicacao da linguagem correspondente.

## GitHub Environments

Crie estes environments no repositorio:

- `pypi`
- `npm`
- `nuget`
- `pubdev`

Se quiser aprovacao manual antes da publicacao, configure as protection rules no proprio environment.
No caso do pub.dev com GitHub Actions, configure o package no painel Admin do pub.dev para aceitar publicacao automatizada do repositório `ezequiel88/sdk-ingestao-vetorial` usando o padrao de tag `sdk-flutter-v{{version}}`. Se quiser atrelar isso ao environment do GitHub, configure o mesmo nome de environment tambem no pub.dev.

Se o repositório remoto ainda estiver vazio, faça antes o primeiro push da branch `main`. Sem esse push inicial, os workflows e os environments ainda não existirão no GitHub.

Para automatizar a criação dos environments, use:

```powershell
$env:GITHUB_TOKEN = "<token-com-acesso-admin-ao-repo>"
./scripts/setup-github-release.ps1
```

## Secrets por environment

### `pypi`

- `PYPI_API_TOKEN`: token da conta/projeto no PyPI.

### `npm`

- `NPM_TOKEN`: token com permissao de publicacao no npm para o pacote `ingestao-vetorial-sdk`.

### SDK PHP

- O SDK PHP foi movido para o repositório dedicado `ezequiel88/sdk-php-ingestao-vetorial`.
- O release do Packagist nao faz mais parte deste monorepo.
- Qualquer configuracao de `PACKAGIST_USERNAME` e `PACKAGIST_API_TOKEN` deve existir apenas no repositório dedicado.

### `nuget`

- `NUGET_API_KEY`: chave de publicacao do NuGet para `IngestaoVetorial.SDK`.

### `pubdev`

- Nenhum secret adicional e necessario no modelo atual com GitHub Actions + OIDC.
- O pacote `ingestao_vetorial_flutter_sdk` precisa estar previamente cadastrado no pub.dev e com publicacao automatizada habilitada no painel Admin.
- A primeira publicacao do pacote continua sendo manual com `dart pub publish`.

## Permissoes do repositorio

- `Contents: write` para o workflow `sdk-release-main.yml`, pois ele cria commit de bump e publica tags.
- `Contents: write` tambem para o workflow do Go, porque ele cria GitHub Release.

## Checklist antes do primeiro release

1. Confirmar que os nomes publicados batem com os manifests:
   - Python: `ingestao-vetorial-sdk`
   - npm: `ingestao-vetorial-sdk`
   - NuGet: `IngestaoVetorial.SDK`
   - pub.dev: `ingestao_vetorial_flutter_sdk`
2. Confirmar ownership/permissoes nas plataformas externas para esses nomes.
3. Fazer o push inicial da branch `main`, caso o repositório GitHub ainda esteja sem commits.
4. Confirmar que a branch default do repositorio e `main`.
5. Confirmar que GitHub Actions tem permissao para criar e enviar tags.
6. Criar pelo menos um arquivo em `.changeset/` para validar o fluxo de bump/tag.

## Validacao recomendada

1. Abrir um PR com um changeset pequeno.
2. Confirmar que `sdk-ci.yml` exige changeset e que os testes das linguagens passam.
3. Fazer merge na `main`.
4. Confirmar que `sdk-release-main.yml`:
   - gera `sdk-release-plan.json`
   - atualiza versoes
   - remove o changeset consumido
   - cria o commit `chore(release): version SDKs [skip sdk-release]`
   - publica as tags
5. Confirmar a execucao dos workflows por tag:
   - `sdk-release-python.yml`
   - `sdk-release-js.yml`
   - `sdk-release-csharp.yml`
   - `sdk-release-go.yml`
   - `sdk-release-flutter.yml`
6. Validar os artefatos publicados nas plataformas externas.

## Tags esperadas

- Python: `sdk-python-vX.Y.Z`
- JavaScript: `sdk-js-vX.Y.Z`
- C#: `sdk-csharp-vX.Y.Z`
- Go: `sdk/go/vX.Y.Z`
- Flutter: `sdk-flutter-vX.Y.Z`