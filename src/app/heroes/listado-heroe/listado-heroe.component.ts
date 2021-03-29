import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-listado-heroe',
  templateUrl: './listado-heroe.component.html',
  styleUrls: ['./listado-heroe.component.css']
})
export class ListadoHeroeComponent implements OnInit {

  heroeBorrado: string = '';
  muestraBorrado: boolean = false;  

  heroes: string[] = ['Spiderman', 'Ironman', 'Hulk', 'Falcon', 'Thor'];

  constructor() { }

  ngOnInit(): void {
  }

  borrarHeroe = (): void => {
    // si es undefined || (significa entonces) regresa Ya no hay heroes
     this.heroeBorrado = this.heroes.pop() || '';

     this.muestraBorrado = (this.heroeBorrado.length > 0) ? true : false;
  }

}
