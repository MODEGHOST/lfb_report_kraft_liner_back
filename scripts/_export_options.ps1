$ErrorActionPreference = 'Stop'
$item = Get-ChildItem 'C:\Users\sa.data02\Downloads\*.xls' | Where-Object { $_.Length -eq 2032128 } | Select-Object -First 1
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$wb = $excel.Workbooks.Open($item.FullName, 0, $true)
try {
  $ws = $null
  foreach ($s in $wb.Worksheets) {
    if ([int]$s.UsedRange.Columns.Count -ge 80) { $ws = $s }
  }
  if (-not $ws) { throw 'sheet missing' }
  $rows = [int]$ws.UsedRange.Rows.Count
  $lines = New-Object System.Collections.Generic.List[string]
  for ($r = 5; $r -le $rows; $r++) {
    $code = ([string]$ws.Cells.Item($r, 2).Text).Trim()
    $name = ([string]$ws.Cells.Item($r, 3).Text).Trim()
    if ($code -eq '' -and $name -eq '') { continue }
    $use = ([string]$ws.Cells.Item($r, 11).Text).Trim()
    $paper = ([string]$ws.Cells.Item($r, 12).Text).Trim()
    $rope = ([string]$ws.Cells.Item($r, 13).Text).Trim()
    $film = ([string]$ws.Cells.Item($r, 14).Text).Trim()
    $lines.Add(($code + "`t" + $name + "`t" + $use + "`t" + $paper + "`t" + $rope + "`t" + $film))
  }
  [System.IO.File]::WriteAllLines('D:\report_data_salescustomer\backend\scripts\_options.tsv', $lines, [System.Text.UTF8Encoding]::new($false))
} finally {
  $wb.Close($false)
  $excel.Quit()
}
