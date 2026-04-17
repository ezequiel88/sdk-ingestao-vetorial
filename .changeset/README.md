# Changesets dos SDKs

Cada PR que altere o comportamento, a API publica ou o empacotamento de um SDK deve incluir um arquivo em `.changeset/`.

Formato:

```md
---
python: patch
js: minor
---

Resumo curto das mudancas que justificam o release.
```

Chaves aceitas:

- `python`
- `js`
- `csharp`
- `go`
- `flutter`

Valores aceitos:

- `patch`
- `minor`
- `major`

Regras:

- Use apenas os SDKs impactados no front matter.
- Um mesmo changeset pode versionar mais de um SDK.
- O workflow da `main` consome os changesets pendentes, aplica o bump de versao, remove os arquivos consumidos e cria as tags automaticamente.
