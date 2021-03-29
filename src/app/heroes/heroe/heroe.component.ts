import { Component } from "@angular/core";

@Component ({
    selector: 'app-heroe',
    templateUrl: 'heroe.component.html'
})

export class HeroeComponente {

    nombre: string = 'Ironman';
    edad: number = 45;

    // getter
    get nombreCapitalize(): string{
        return this.nombre.toUpperCase();
    }

    // metodo/funcion
    getDatosHeroe(): string{
        return `${this.nombre} tiene ${this.edad} años de edad.`;
    }

    cambiarNombre = (): void =>{
        this.nombre = (this.nombre == 'Ironman') ? 'Spiderman' : 'Ironman';   
    }

    cambiarEdad = (): void =>{
        this.edad = (this.edad == 45) ? 27 : 45;   
    }
}