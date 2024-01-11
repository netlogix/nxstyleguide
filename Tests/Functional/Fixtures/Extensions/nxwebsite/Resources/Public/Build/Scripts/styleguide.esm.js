import { hello } from './hello.js';

export{hello as hello1}from"./hello.js";
import{world as world1}from"./world.js";
import"./foo.js";

const helloWorld = () => {
    console.log(hello('World'));
    console.log(world1('Good Morning'));
    console.log(foo());
}
