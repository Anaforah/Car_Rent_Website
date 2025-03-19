let btn = document.getElementById('btn');
let btn1 = document.getElementsByClassName('toggle-btn1')[0];
let btn2 = document.getElementsByClassName('toggle-btn2')[0];
let isCliente = true;  // Define o estado inicial como "Cliente"
const userTypeInput = document.getElementById('userType');

function updateButtonState() {
    if (isCliente) {
        btn.style.left = '0';  // Move o #btn para a metade esquerda
        btn1.style.color = '#E6E6E6';  // Muda a cor do botão "Cliente"
        btn2.style.color = '#1B1B1B';  // Restaura a cor do botão "Administrador"
        console.log("Estado atual: Cliente");
        userTypeInput.value = 'Cliente';
    } else {
        btn.style.left = '50%';  // Move o #btn para a metade direita
        btn2.style.color = '#E6E6E6';  // Muda a cor do botão "Administrador"
        btn1.style.color = '#1B1B1B';  // Restaura a cor do botão "Cliente"
        console.log("Estado atual: Administrador");
        userTypeInput.value = 'Administrador';
    }
}

function leftClick() {
    isCliente = true;  // Define o estado como "Cliente"
    updateButtonState();
}

function rightClick() {
    isCliente = false;  // Define o estado como "Administrador"
    updateButtonState();
}

// Inicializa o estado ao carregar a página
updateButtonState();

