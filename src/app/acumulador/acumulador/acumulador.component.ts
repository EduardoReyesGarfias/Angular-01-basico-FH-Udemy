import { Component } from '@angular/core';

@Component({
    selector : 'app-acumulador',
    template : `
        <h1>{{ title }}</h1>
        <h2> La base es {{ base }}</h2>

        <button (click) = "acumulador( base )" > {{base}} </button>

        <span> Número inicial: {{ numero }} </span>

        <button (click) = " acumulador( -base ) " > -{{base}} </button>
    `
})
export class AcumuladorComponente{

    title = 'Acumulador App';
    numero : number = 10;
    base : number = 5;

    // Funcion para ir acumulando
    acumulador( valor : number ) {
        this.numero = this.numero + valor;
    }

}