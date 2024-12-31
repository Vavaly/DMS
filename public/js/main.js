// // // public/js/main.js
// // document.addEventListener("DOMContentLoaded", function () {
// //   // Mengatur ucapan salam berdasarkan waktu
// //   function setGreeting() {
// //     const now = new Date();
// //     const hour = now.getHours();
// //     const greetingElement = document.getElementById("greeting");

// //     if (hour < 11) greetingElement.textContent = "Selamat Pagi";
// //     else if (hour < 15) greetingElement.textContent = "Selamat Siang";
// //     else if (hour < 18) greetingElement.textContent = "Selamat Sore";
// //     else greetingElement.textContent = "Selamat Malam";
// //   }

// //   // Fungsi untuk menampilkan file di tabel
// //   function displayDocuments(documents) {
// //     const tableBody = document.querySelector(".document-table tbody");
// //     tableBody.innerHTML = ""; // Kosongkan tabel

// //     documents.forEach((doc) => {
// //       const row = `
// //                 <tr>
// //                     <td>${doc.filename}</td>
// //                     <td>${doc.type}</td>
// //                     <td>${doc.size} MB</td>
// //                     <td>${doc.uploadDate}</td>
// //                     <td>${doc.owner}</td>
// //                     <td>
// //                         <button class="btn btn-sm btn-info">Baca</button>
// //                         <button class="btn btn-sm btn-warning">Edit</button>
// //                         <button class="btn btn-sm btn-danger">Hapus</button>
// //                     </td>
// //                 </tr>
// //             `;
// //       tableBody.innerHTML += row;
// //     });
// //   }

// //   // Inisialisasi
// //   setGreeting();

// // });

// // document.addEventListener("DOMContentLoaded", function () {
// //   const profileImage = document.getElementById("profileImage");
// //   const dropdownMenu = document.getElementById("dropdownMenu");

// //   profileImage.addEventListener("click", function () {
// //     dropdownMenu.classList.toggle("hidden");
// //   });

// //   // Menutup dropdown ketika pengguna mengklik di luar menu
// //   document.addEventListener("click", function (event) {
// //     if (!profileImage.contains(event.target) && !dropdownMenu.contains(event.target)) {
// //       dropdownMenu.classList.add("hidden");
// //     }
// //   });
// // });

// // // main.js

// function updateGreetingAndTime() {
//   const greetingElement = document.getElementById("greeting");
//   const dateTimeElement = document.getElementById("date-time");

//   const now = new Date();
//   const hours = now.getHours();
//   const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
//   const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

//   // Tentukan salam berdasarkan waktu
//   let greeting = "Selamat Pagi";
//   if (hours >= 12 && hours < 18) {
//     greeting = "Selamat Siang";
//   } else if (hours >= 18 && hours < 24) {
//     greeting = "Selamat Malam";
//   }

//   // Format tanggal dan waktu
//   const day = days[now.getDay()];
//   const date = now.getDate();
//   const month = months[now.getMonth()];
//   const year = now.getFullYear();
//   const formattedTime = now.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit" });

//   // Perbarui elemen
//   greetingElement.textContent = `${greeting}, Rival`;
//   dateTimeElement.textContent = `${day}, ${date} ${month} ${year} - ${formattedTime}`;
// }

// // // Perbarui setiap detik
// // setInterval(updateGreetingAndTime, 1000);

// // // Panggil segera untuk inisialisasi
// // updateGreetingAndTime();

// // //membuka file
// // // Menangani perubahan file yang dipilih
// // document.getElementById('file-upload').addEventListener('change', function() {
// //   const fileName = this.files[0] ? this.files[0].name : "Tidak ada file yang dipilih";
// //   alert("File yang dipilih: " + fileName);
// // });

// <script>
// document.addEventListener('DOMContentLoaded', function() {
//     const passwordInput = document.getElementById('password');
//     const togglePasswordBtn = document.getElementById('togglePassword');
//     const passwordStrengthBars = document.querySelectorAll('.password-strength');
//     const forgotPasswordLink = document.getElementById('forgotPassword');

//     // Toggle password visibility
//     togglePasswordBtn.addEventListener('click', function() {
//         const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
//         passwordInput.setAttribute('type', type);
//     });

//     // Password strength indicator
//     passwordInput.addEventListener('input', function() {
//         const password = passwordInput.value;
//         let strength = 0;

//         // Check password length
//         if (password.length >= 8) strength++;

//         // Check for uppercase, lowercase, and numbers
//         if (/[A-Z]/.test(password)) strength++;
//         if (/[a-z]/.test(password)) strength++;
//         if (/[0-9]/.test(password)) strength++;

//         // Update strength bars
//         passwordStrengthBars.forEach((bar, index) => {
//             bar.style.width = index < strength ? '33%' : '0';
//         });
//     });

//     // Forgot Password modal (placeholder)
//     forgotPasswordLink.addEventListener('click', function(e) {
//         e.preventDefault();
//         alert('Please contact the system administrator to reset your password.');
//     });
// });
// </script>

// document.querySelector('.dokumen-saya').addEventListener('click', function() {
//     const submenu = this.querySelector('ul');
//     submenu.classList.toggle('hidden');
// });

// <script>
//     document.addEventListener('DOMContentLoaded', function () {
//         const profileToggle = document.getElementById('profileToggle');
//         const dropdownMenu = document.getElementById('dropdownMenu');

//         profileToggle.addEventListener('click', () => {
//             if (dropdownMenu.classList.contains('hidden')) {
//                 dropdownMenu.classList.remove('hidden', 'opacity-0');
//                 dropdownMenu.classList.add('opacity-100');
//             } else {
//                 dropdownMenu.classList.add('hidden', 'opacity-0');
//                 dropdownMenu.classList.remove('opacity-100');
//             }
//         });

//         // Menutup dropdown ketika klik di luar
//         window.addEventListener('click', (e) => {
//             if (!profileToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
//                 dropdownMenu.classList.add('hidden', 'opacity-0');
//                 dropdownMenu.classList.remove('opacity-100');
//             }
//         });
//     });
//     </script>

document.addEventListener("DOMContentLoaded", function () {
  const profileToggle = document.getElementById("profileToggle");
  const dropdownMenu = document.getElementById("dropdownMenu");

  profileToggle.addEventListener("click", function (e) {
    e.stopPropagation();
    if (dropdownMenu.classList.contains("scale-y-0")) {
      dropdownMenu.classList.remove("scale-y-0", "opacity-0");
      dropdownMenu.classList.add("scale-y-100", "opacity-100");
    } else {
      dropdownMenu.classList.remove("scale-y-100", "opacity-100");
      dropdownMenu.classList.add("scale-y-0", "opacity-0");
    }
  });

  document.addEventListener("click", function (e) {
    if (!dropdownMenu.contains(e.target)) {
      dropdownMenu.classList.remove("scale-y-100", "opacity-100");
      dropdownMenu.classList.add("scale-y-0", "opacity-0");
    }
  });
});

// Script untuk mengatur modal
document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("userModal");
  const openModalBtn = document.getElementById("openModal");
  const closeModalBtn = document.getElementById("closeModal");
  const cancelModalBtn = document.getElementById("cancelModal");
  const modalContent = modal.querySelector(".bg-white");

  function openModal() {
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    // Beri waktu untuk animasi fade in
    setTimeout(() => {
      modal.classList.add("opacity-100");
      modalContent.classList.remove("scale-95", "opacity-0");
      modalContent.classList.add("scale-100", "opacity-100");
    }, 50);
  }

  function closeModal() {
    modalContent.classList.remove("scale-100", "opacity-100");
    modalContent.classList.add("scale-95", "opacity-0");
    modal.classList.remove("opacity-100");
    // Beri waktu untuk animasi fade out
    setTimeout(() => {
      modal.classList.remove("flex");
      modal.classList.add("hidden");
    }, 300);
  }

  openModalBtn.addEventListener("click", openModal);
  closeModalBtn.addEventListener("click", closeModal);
  cancelModalBtn.addEventListener("click", closeModal);

  // Tutup modal jika mengklik area di luar modal
  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      closeModal();
    }
  });
});
