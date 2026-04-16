$a = "f655ba9d09a112d4968c63579db590b4" # Key
$b = "98344c2eee86c3994890592585b49f80" # IV
$c = "53592f60b4f8b8b35c6de35028df0248" # Data

function toBytes($hex) {
    $res = New-Object byte[] ($hex.Length / 2)
    for($i=0; $i -lt $hex.Length; $i+=2){
        $res[$i/2] = [System.Convert]::ToByte($hex.Substring($i, 2), 16)
    }
    return $res
}

$kb = toBytes($a)
$ib = toBytes($b)
$db = toBytes($c)

$aes = [System.Security.Cryptography.Aes]::Create()
$aes.Key = $kb
$aes.IV = $ib
$aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
$aes.Padding = [System.Security.Cryptography.PaddingMode]::None

$dec = $aes.CreateDecryptor()
$ms = New-Object System.IO.MemoryStream @(,$db)
$cs = New-Object System.Security.Cryptography.CryptoStream($ms, $dec, [System.Security.Cryptography.CryptoStreamMode]::Read)
$rb = New-Object byte[] $db.Length
$len = $cs.Read($rb, 0, $rb.Length)

$hex = ""
foreach($byte in $rb){
    $hex += $byte.ToString("x2")
}
Write-Output $hex
