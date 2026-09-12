param(
    [Parameter(Mandatory = $true)][string]$OutputRoot,
    [Parameter(Mandatory = $true)][string]$PublicKeyFile,
    [string]$PhpBinary = 'php'
)

$signingDirectory = Join-Path $OutputRoot '.signing'
$privateKeyFile = Join-Path $signingDirectory 'private.pem'
if ((Test-Path -LiteralPath $privateKeyFile) -and (Get-Item -LiteralPath $privateKeyFile).Length -gt 0) {
    throw 'The private signing key already exists.'
}

New-Item -ItemType Directory -Path $signingDirectory -Force | Out-Null
$randomFile = Join-Path $signingDirectory '.rnd'
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$bytes = New-Object byte[] 256
$rng.GetBytes($bytes)
[System.IO.File]::WriteAllBytes($randomFile, $bytes)
$rng.Dispose()

$env:OPENSSL_CONF = Join-Path $PSScriptRoot 'openssl.cnf'
$env:RANDFILE = $randomFile
& $PhpBinary (Join-Path $PSScriptRoot 'generate-release-key.php') $OutputRoot $PublicKeyFile
if ($LASTEXITCODE -ne 0) {
    throw 'The release signing key could not be generated.'
}
Remove-Item -LiteralPath $randomFile -Force
