$ErrorActionPreference = 'Stop'
$item = Get-ChildItem 'C:\Users\sa.data02\Downloads\*.xls' | Where-Object { $_.Length -eq 2032128 } | Select-Object -First 1
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$wb = $excel.Workbooks.Open($item.FullName, 0, $true)
$ws = $wb.Worksheets.Item(1)
$counts = @{}
for ($i = 5; $i -le 8; $i++) { $counts[$i] = @{} }
$samples = New-Object System.Collections.Generic.List[string]
$rows = [int]$ws.UsedRange.Rows.Count
for ($r = 3; $r -le $rows; $r++) {
  $code = [string]$ws.Cells.Item($r, 1).Text
  $bits = @()
  for ($c = 5; $c -le 8; $c++) {
    $cell = $ws.Cells.Item($r, $c)
    $text = [string]$cell.Text
    $formula = [string]$cell.Formula
    if ($text -eq '') { $key = '(blank)' } else { $key = $text }
    if (-not $counts[$c].ContainsKey($key)) { $counts[$c][$key] = 0 }
    $counts[$c][$key]++
    if ($r -le 8) {
      $bits += ($c.ToString() + ' text=' + $text + ' formula=' + $formula)
    }
  }
  if ($r -le 8) { $samples.Add($code + ' | ' + ($bits -join ' ; ')) }
}
$out = New-Object System.Collections.Generic.List[string]
$out.AddRange($samples)
foreach ($c in 5..8) {
  $out.Add('COL' + $c)
  foreach ($k in $counts[$c].Keys) {
    $out.Add('  ' + $k + '=' + $counts[$c][$k])
  }
}
$wb.Close($false)
$excel.Quit()
[System.IO.File]::WriteAllLines('D:\report_data_salescustomer\backend\scripts\_peek_flag_values.txt', $out, [System.Text.UTF8Encoding]::new($false))
