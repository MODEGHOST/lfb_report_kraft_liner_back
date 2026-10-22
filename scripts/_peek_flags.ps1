$ErrorActionPreference = 'Stop'
$item = Get-ChildItem 'C:\Users\sa.data02\Downloads\*.xls' | Where-Object { $_.Length -eq 2032128 } | Select-Object -First 1
if (-not $item) { throw 'xls not found' }
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$wb = $excel.Workbooks.Open($item.FullName, 0, $true)
$out = New-Object System.Collections.Generic.List[string]
$out.Add('FILE=' + $item.FullName)
foreach ($ws in $wb.Worksheets) {
  $used = $ws.UsedRange
  $rows = [int]$used.Rows.Count
  $cols = [int]$used.Columns.Count
  $out.Add('SHEET=' + $ws.Name + ' rows=' + $rows + ' cols=' + $cols)
  $max = [Math]::Min(4, $rows)
  $maxc = [Math]::Min(56, $cols)
  for ($r = 1; $r -le $max; $r++) {
    $cells = @()
    for ($c = 1; $c -le $maxc; $c++) {
      $v = [string]$ws.Cells.Item($r, $c).Text
      if ($v -ne '') { $cells += ($c.ToString() + ':' + $v) }
    }
    $out.Add('R' + $r + ' ' + ($cells -join ' | '))
  }
}
$wb.Close($false)
$excel.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($wb) | Out-Null
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null
[System.IO.File]::WriteAllLines('D:\report_data_salescustomer\backend\scripts\_peek_flags.txt', $out, [System.Text.UTF8Encoding]::new($false))
