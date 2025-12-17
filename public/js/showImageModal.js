function showImageModal(src) {
    const modalImg = document.getElementById("modalImage");
    modalImg.src = src;
    const modal = new bootstrap.Modal(document.getElementById("imageModal"));
    modal.show();
}
