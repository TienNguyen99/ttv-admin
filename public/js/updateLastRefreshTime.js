function updateLastRefreshTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString("vi-VN");
    document.getElementById("lastUpdate").textContent = timeStr;
}
