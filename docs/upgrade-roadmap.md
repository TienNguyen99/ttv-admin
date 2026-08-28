# Ke hoach nang cap he thong

Tai lieu nay la thu tu nang cap bat buoc de giu he thong kho dang chay on dinh.

## Nguyen tac

- TSoft chi duoc doc. Moi migration va thao tac ghi chi dung connection `internal`.
- Moi pha phai chay `composer check` thanh cong truoc khi dua vao su dung.
- Khong nang PHP va Laravel cung luc.
- Luon thu tren ban sao database noi bo truoc khi chay tren may dang dung.

## Pha 1 - Duong chuan hien tai

- PHP toi thieu: 7.4.
- Laravel: 8.x.
- Lenh kiem tra: `composer check`.
- Tat ca phep tinh ton, FIFO, BOM va kiem ke phai co test truoc khi refactor.

## Pha 2 - PHP 8.3 song song

1. Cai PHP 8.3 vao thu muc rieng, khong ghi de XAMPP hien tai.
2. Chay `composer install` va `composer check` bang PHP 8.3.
3. Sua warning va dependency khong tuong thich.
4. Chi chuyen port `8889` sau khi toan bo test dat.

## Pha 3 - Laravel

Nang tung major tren nhanh rieng: 8 -> 9 -> 10, sau do danh gia phien ban dich theo kha nang tuong thich cua Google API, Excel va QR. Moi buoc deu phai chay migration tren database thu nghiem va `composer check`.

## Pha 4 - Tach module

Thu tu tach service:

1. Kho va so giao dich ton.
2. Xuat vat tu va FIFO.
3. Lenh san xuat va cong doan.
4. BOM va dinh muc.
5. Dong bo Google Sheet.
6. Bao cao Excel.

Controller chi validate request va tra response. Query, tinh toan va transaction nam trong service.

## Tieu chi hoan thanh

- Khong ghi vao TSoft.
- Khong thay doi ket qua ton kho cu.
- Khong co query danh sach khong gioi han.
- API danh sach co phan trang hoac `limit` ro rang.
- Xoa/sua chung tu co audit log.
- `composer check` thanh cong.
