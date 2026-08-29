# BOM va dinh muc noi bo

## Nguon du lieu

- `internal_product_bom_profiles`: BOM mau hien hanh cua mot ma thanh pham. Duoc phep sua va tang `revision`.
- `internal_product_bom_lines`: vat tu, vai tro, don vi, dinh muc, hao hut va cong doan cua BOM mau.
- `internal_production_order_bom_snapshots`: ban dong bang theo tung dong lenh san xuat/BTP.
- Snapshot da chot khong duoc tinh lai khi BOM mau thay doi.
- Tat ca bang tren dung connection `internal`. TSoft chi duoc doc.

## Cach tinh

- `consumption`: `so luong lenh * dinh muc * (1 + hao hut / 100)`.
- `yield`: `so luong lenh / nang suat * (1 + hao hut / 100)`.
- `formula`: chia tong hon hop tren mot san pham theo ty le tung nguyen lieu, sau do quy doi ve don vi cua vat tu.
- Don vi vat ly nhu tam, cuon, thung, cai, bo va PCS duoc lam tron len khi tinh theo nang suat.
- `exact_required_quantity` la ket qua chua lam tron; `required_quantity` la so cap vat tu.

## Luong nghiep vu

1. Luu BOM mau cho ma thanh pham.
2. Chon lenh san xuat hoac lenh BTP de xem nhu cau tam tinh.
3. Chot BOM de tao snapshot theo revision hien tai.
4. Tong hop nhieu lenh de tinh nhu cau, da xuat, de xuat, ton co the cap va thieu.
5. Ton duoc giu cho tung dong theo thu tu danh sach; mot luong ton khong duoc bao du cho nhieu lenh.
6. Nguoi dung sua cot thuc xuat truoc khi tao phieu xuat vat tu.

## API chinh

- `GET /api/dinh-muc-san-xuat?item_code=...`
- `POST /api/dinh-muc-san-xuat/luu`
- `GET /api/dinh-muc-san-xuat/nhu-cau?production_order=...`
- `POST /api/dinh-muc-san-xuat/chot-lenh`
- `GET /api/dinh-muc-san-xuat/tong-hop-cap-vat-tu?production_orders[]=...`

Moi thay doi cong thuc phai chay `composer check` va bo test `InternalProductBomControllerTest`.
