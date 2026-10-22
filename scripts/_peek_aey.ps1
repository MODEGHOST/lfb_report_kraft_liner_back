$ErrorActionPreference = 'Stop'
$item = Get-ChildItem 'C:\Users\sa.data02\Downloads\*.xls' | Where-Object { $_.Length -eq 2032128 } | Select-Object -First 1
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$wb = $excel.Workbooks.Open($item.FullName, 0, $true)
$ws = $null
foreach ($s in $wb.Worksheets) {
  if ([int]$s.UsedRange.Columns.Count -ge 80) { $ws = $s }
}
if (-not $ws) { throw 'sheet missing' }
$out = New-Object System.Collections.Generic.List[string]
$out.Add('SHEET=' + $ws.Name)
$rows = [int]$ws.UsedRange.Rows.Count
$out.Add('rows=' + $rows)
for ($r = 2; $r -le 6; $r++) {
  $bits = @()
  for ($c = 2; $c -le 14; $c++) {
    $cell = $ws.Cells.Item($r, $c)
    $text = [string]$cell.Text
    $formula = [string]$cell.Formula
    if ($text -ne '' -or $formula -ne '') {
      $bits += ($c.ToString() + ' t=' + $text + ' f=' + $formula)
    }
  }
  $out.Add('R' + $r + ' ' + ($bits -join ' | '))
}
$counts = @{}
for ($i = 11; $i -le 14; $i++) { $counts[$i] = @{} }
$data = 0
for ($r = 5; $r -le $rows; $r++) {
  $code = ([string]$ws.Cells.Item($r, 2).Text).Trim()
  if ($code -eq '') { continue }
  $data++
  for ($c = 11; $c -le 14; $c++) {
    $text = [string]$ws.Cells.Item($r, $c).Text
    if ($text -eq '') { $key = '(blank)' } else { $key = $text }
    if (-not $counts[$c].ContainsKey($key)) { $counts[$c][$key] = 0 }
    $counts[$c][$key]++
  }
}
$out.Add('codes=' + $data)
foreach ($c in 11..14) {
  $out.Add('COL' + $c)
  foreach ($k in $counts[$c].Keys) { $out.Add('  [' + $k + ']=' + $counts[$c][$k]) }
}
$wb.Close($false)
$excel.Quit()
[System.IO.File]::WriteAllLines('D:\report_data_salescustomer\backend\scripts\_peek_aey.txt', $out, [System.Text.UTF8Encoding]::new($false))
