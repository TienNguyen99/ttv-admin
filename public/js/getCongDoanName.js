function getCongDoanName(maKo) {
    if (!maKo) return "";

    // Chuyển về string
    const maKoStr = String(maKo);

    // Map với cả dạng có 0 và không có 0
    const congDoanMap = {
        5: "QC",
        "05": "QC",
        6: "Nhập kho",
        "06": "Nhập kho",
        9: "Xuất kho",
        "09": "Xuất kho",
    };

    return congDoanMap[maKoStr] || maKoStr;
}
