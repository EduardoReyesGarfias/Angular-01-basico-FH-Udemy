import { CommonModule } from '@angular/common';
import { NgModule } from '@angular/core';
import { HeroeComponente } from './heroe/heroe.component';
import { ListadoHeroeComponent } from './listado-heroe/listado-heroe.component';

@NgModule({
    declarations: [
        HeroeComponente,
        ListadoHeroeComponent
    ],
    imports: [
        CommonModule
    ],
    exports: [
        ListadoHeroeComponent
    ]
})
export class HeroesModule{

}