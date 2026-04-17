param(
    [Parameter(Mandatory = $false)]
    [string]$Owner = "ezequiel88",

    [Parameter(Mandatory = $false)]
    [string]$Repo = "sdk-ingestao-vetorial",

    [Parameter(Mandatory = $false)]
    [string]$GitHubToken = $env:GITHUB_TOKEN
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($GitHubToken)) {
    throw "Defina GITHUB_TOKEN antes de executar o script. O token precisa ter permissao para administrar o repositorio."
}

$headers = @{
    Accept = "application/vnd.github+json"
    Authorization = "Bearer $GitHubToken"
    "X-GitHub-Api-Version" = "2022-11-28"
}

$environments = @("pypi", "npm", "packagist", "nuget", "pubdev")

foreach ($environment in $environments) {
    $uri = "https://api.github.com/repos/$Owner/$Repo/environments/$environment"
    Write-Host "Criando/atualizando environment '$environment'..."
    Invoke-RestMethod -Method Put -Uri $uri -Headers $headers | Out-Null
}

Write-Host ""
Write-Host "Environments configurados com sucesso."
Write-Host "Secrets que ainda precisam ser cadastrados manualmente na UI do GitHub:"
Write-Host "- pypi: PYPI_API_TOKEN"
Write-Host "- npm: NPM_TOKEN"
Write-Host "- packagist: PACKAGIST_USERNAME, PACKAGIST_API_TOKEN"
Write-Host "- nuget: NUGET_API_KEY"
Write-Host "- pubdev: nenhum secret obrigatorio no modelo atual com OIDC"
Write-Host ""
Write-Host "Tela de environments: https://github.com/$Owner/$Repo/settings/environments"
Write-Host "Tela de secrets: https://github.com/$Owner/$Repo/settings/secrets/actions"